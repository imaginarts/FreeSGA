<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\SurveyResponse;
use App\Models\Ticket;
use App\Models\TicketNotification;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/** Direitos do titular (LGPD art. 18) e retenção de dados pessoais. */
class PrivacyService
{
    public const ANONYMIZED_PREFIX = 'ANON-';

    /** Todos os dados pessoais do cliente, para entrega ao titular. */
    public function export(Customer $customer): array
    {
        $customer->load(['tickets.unit', 'tickets.service', 'appointments.unit', 'appointments.service']);

        Audit::log('privacy.export', "Exportação dos dados do cliente #{$customer->id}", $customer);

        return [
            'gerado_em' => now()->toIso8601String(),
            'cliente' => $customer->only([
                'name', 'document', 'email', 'phone', 'gender', 'notes', 'address_zip', 'address_street',
                'address_number', 'address_complement', 'address_city', 'address_state', 'address_country',
            ]) + ['birth_date' => $customer->birth_date?->toDateString(), 'cadastrado_em' => $customer->created_at?->toIso8601String()],
            'atendimentos' => $customer->tickets->map(fn (Ticket $t) => [
                'senha' => $t->code(),
                'unidade' => $t->unit->name,
                'servico' => $t->service->name,
                'status' => $t->status->label(),
                'chegada' => $t->localTime($t->arrived_at, 'c'),
                'encerramento' => $t->localTime($t->finished_at, 'c'),
                'avaliacao' => $t->surveyResponse?->only(['score', 'comment']),
            ])->values(),
            'agendamentos' => $customer->appointments->map(fn ($a) => [
                'unidade' => $a->unit->name,
                'servico' => $a->service->name,
                'data' => $a->date->toDateString(),
                'hora' => substr($a->time, 0, 5),
                'situacao' => $a->status->label(),
            ])->values(),
        ];
    }

    /**
     * Remove os dados pessoais mantendo o histórico estatístico (senhas, tempos, notas).
     * Irreversível.
     */
    public function anonymize(Customer $customer, string $reason = 'solicitação do titular'): void
    {
        DB::transaction(function () use ($customer) {
            $ticketIds = $customer->tickets()->pluck('id');

            Ticket::whereIn('id', $ticketIds)->update(['notify_phone' => null]);
            Ticket::whereIn('id', $ticketIds)->whereNotNull('meta')->get()->each(function (Ticket $t) {
                $meta = $t->meta;
                unset($meta['document']);
                $t->forceFill(['meta' => $meta ?: null])->saveQuietly();
            });
            SurveyResponse::whereIn('ticket_id', $ticketIds)->update(['comment' => null]);
            TicketNotification::whereIn('ticket_id', $ticketIds)->delete();

            $customer->forceFill([
                'name' => 'Cliente anonimizado',
                'document' => self::ANONYMIZED_PREFIX.$customer->id,
                'email' => null,
                'phone' => null,
                'birth_date' => null,
                'gender' => null,
                'notes' => null,
                'address_country' => null,
                'address_zip' => null,
                'address_state' => null,
                'address_city' => null,
                'address_street' => null,
                'address_number' => null,
                'address_complement' => null,
            ])->saveQuietly();
        });

        Audit::log('privacy.anonymized', "Cliente #{$customer->id} anonimizado ({$reason})", $customer);
    }

    public function isAnonymized(Customer $customer): bool
    {
        return str_starts_with($customer->document, self::ANONYMIZED_PREFIX);
    }

    /**
     * Rotina diária: anonimiza clientes sem movimento há N meses (se habilitado),
     * apaga CPFs avulsos de senhas antigas, histórico de mensagens e auditoria vencida.
     */
    public function cleanup(): array
    {
        $privacy = Setting::get('privacy');
        $audit = Setting::get('audit');
        $result = ['customers' => 0, 'tickets' => 0, 'notifications' => 0, 'audit' => 0];

        if ($privacy['retention_enabled']) {
            $cutoff = now()->subMonths(max(1, (int) $privacy['retention_months']));

            $inactive = Customer::where('document', 'not like', self::ANONYMIZED_PREFIX.'%')
                ->where('created_at', '<', $cutoff)
                ->whereDoesntHave('tickets', fn ($q) => $q->where('arrived_at', '>=', $cutoff))
                ->whereDoesntHave('appointments', fn ($q) => $q->where('date', '>=', $cutoff->toDateString()))
                ->get();

            foreach ($inactive as $customer) {
                $this->anonymize($customer, 'retenção automática');
            }
            $result['customers'] = $inactive->count();

            // CPF informado no totem sem cadastro fica guardado só na senha
            Ticket::where('arrived_at', '<', $cutoff)->whereNotNull('meta')->get()
                ->filter(fn (Ticket $t) => isset($t->meta['document']))
                ->each(function (Ticket $t) use (&$result) {
                    $meta = $t->meta;
                    unset($meta['document']);
                    $t->forceFill(['meta' => $meta ?: null])->saveQuietly();
                    $result['tickets']++;
                });
        }

        // histórico de mensagens contém telefones: guardado por 90 dias
        $result['notifications'] = TicketNotification::where('created_at', '<', now()->subDays(90))->delete();

        $result['audit'] = AuditLog::where('created_at', '<', now()->subDays(max(30, (int) $audit['retention_days'])))->delete();

        if (array_sum($result) > 0) {
            Audit::log('privacy.cleanup', sprintf(
                'Retenção: %d cliente(s) anonimizado(s), %d CPF(s) removido(s) de senhas, %d mensagem(ns) e %d registro(s) de auditoria apagados',
                $result['customers'], $result['tickets'], $result['notifications'], $result['audit'],
            ));
        }

        return $result;
    }
}
