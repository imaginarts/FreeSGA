<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\Resolution;
use App\Enums\TicketStatus;
use App\Enums\WebhookEvent;
use App\Events\QueueUpdated;
use App\Events\TicketCalled;
use App\Exceptions\TicketException;
use App\Jobs\NotifyNearTickets;
use App\Jobs\SendTicketNotification;
use App\Jobs\SendWebhook;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Kiosk;
use App\Models\PanelCall;
use App\Models\Priority;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\UnitService;
use App\Models\User;
use App\Models\Webhook;
use App\Support\Audit;
use App\Support\Phone;
use App\Support\Privacy;
use App\Support\Realtime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Ciclo de vida da senha: emissão, chamada, atendimento e encerramento. */
class TicketService
{
    public function __construct(private QueueService $queue) {}

    // ------------------------------------------------------------------ emissão

    public function issue(
        Unit $unit,
        int $serviceId,
        Priority $priority,
        ?User $triageUser,
        ?array $customer = null,
        ?Appointment $appointment = null,
        ?array $meta = null,
        ?Kiosk $kiosk = null,
        ?string $notifyPhone = null,
    ): Ticket {
        $notifyPhone = Phone::normalize($notifyPhone);

        if (! $priority->active) {
            throw new TicketException('Prioridade inválida ou inativa.');
        }

        // emissão pelo totem é autorizada pelo próprio totem (ativo e da unidade)
        if ($kiosk) {
            if (! $kiosk->active || $kiosk->unit_id !== $unit->id || ! $unit->feature('kiosk_enabled')) {
                throw new TicketException('Totem desativado.');
            }
        } elseif (! $triageUser || (! $triageUser->is_admin && ! $triageUser->allocationFor($unit))) {
            throw new TicketException('Usuário sem permissão para emitir senhas nesta unidade.');
        }

        $scheduledAt = null;
        if ($appointment) {
            if ($appointment->status !== AppointmentStatus::Scheduled) {
                throw new TicketException('Este agendamento já foi confirmado ou expirou.');
            }
            if ($appointment->unit_id !== $unit->id || $appointment->service_id !== $serviceId) {
                throw new TicketException('O agendamento não corresponde à unidade/serviço informado.');
            }

            $scheduledAt = $appointment->scheduledAt();
            $delay = (int) Setting::get('behavior')['appointment_delay'];
            if (now()->greaterThan($scheduledAt->addMinutes($delay))) {
                throw new TicketException("Agendamento expirado (tolerância de {$delay} minutos).");
            }
        }

        $ticket = DB::transaction(function () use ($unit, $serviceId, $priority, $triageUser, $customer, $appointment, $scheduledAt, $meta, $kiosk, $notifyPhone) {
            /** @var UnitService|null $config */
            $config = UnitService::where('unit_id', $unit->id)
                ->where('service_id', $serviceId)
                ->lockForUpdate()
                ->first();

            if (! $config || ! $config->active || ! $config->service()->where('active', true)->exists()) {
                throw new TicketException('Serviço indisponível nesta unidade.');
            }

            if (! $config->type->accepts($priority->weight)) {
                throw new TicketException('Este serviço não aceita senhas do tipo '.($priority->isPriority() ? 'prioridade' : 'normal').'.');
            }

            if ($config->max_tickets > 0) {
                $issued = Ticket::current()->where('unit_id', $unit->id)->where('service_id', $serviceId)->count();
                if ($issued >= $config->max_tickets) {
                    throw new TicketException('Limite de senhas para este serviço atingido.');
                }
            }

            $number = $config->next_number;
            $next = $number + max(1, $config->increment);
            if ($config->end_number > 0 && $next > $config->end_number) {
                $next = $config->start_number;
            }
            $config->update(['next_number' => $next]);

            if ($appointment) {
                $appointment->update(['status' => AppointmentStatus::Confirmed, 'confirmed_at' => now()]);
            }

            return Ticket::create([
                'unit_id' => $unit->id,
                'service_id' => $serviceId,
                'priority_id' => $priority->id,
                'triage_user_id' => $triageUser?->id,
                'kiosk_id' => $kiosk?->id,
                'customer_id' => $appointment?->customer_id ?? $this->resolveCustomer($customer)?->id,
                'appointment_id' => $appointment?->id,
                'prefix' => $config->prefix,
                'number' => $number,
                'status' => TicketStatus::Issued,
                'scheduled_at' => $scheduledAt?->utc(),
                'arrived_at' => now(),
                'meta' => $meta,
                'notify_phone' => $notifyPhone,
            ]);
        });

        $this->dispatch(WebhookEvent::TicketCreated, $ticket);

        return $ticket;
    }

    private function resolveCustomer(?array $data): ?Customer
    {
        $document = trim($data['document'] ?? '');
        $name = trim($data['name'] ?? '');

        if ($document === '' || $name === '') {
            return null;
        }

        return Customer::firstOrCreate(['document' => $document], array_filter([
            'name' => $name,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]));
    }

    // ------------------------------------------------------------------ chamada

    /** Senha que o atendente está chamando ou atendendo. */
    public function currentTicket(Unit $unit, User $user): ?Ticket
    {
        return Ticket::current()
            ->where('unit_id', $unit->id)
            ->where('user_id', $user->id)
            ->whereIn('status', TicketStatus::inProgress())
            ->latest('called_at')
            ->first();
    }

    /** Chama a próxima senha da fila do atendente (opcionalmente só de um serviço). */
    public function callNext(Unit $unit, User $user, ?int $serviceId = null): Ticket
    {
        $this->ensureReadyToCall($unit, $user);

        $serviceIds = $serviceId ? [$serviceId] : null;

        // tenta algumas vezes: outro atendente pode ter chamado a mesma senha no meio do caminho
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = $this->queue->userQueue($unit, $user, $user->queue_type, $serviceIds, 1)->first();

            if (! $candidate) {
                throw new TicketException('Fila vazia.');
            }

            if ($this->claim($candidate, $user)) {
                return $this->announce($candidate->fresh(), $user, true);
            }
        }

        throw new TicketException('Não foi possível chamar a senha, tente novamente.');
    }

    /** Chama uma senha específica, fora da ordem da fila. */
    public function callTicket(Ticket $ticket, User $user): Ticket
    {
        $this->ensureReadyToCall($ticket->unit, $user);

        if (! $this->claim($ticket, $user)) {
            throw new TicketException('Esta senha não está mais aguardando atendimento.');
        }

        return $this->announce($ticket->fresh(), $user, true);
    }

    public function recall(Ticket $ticket, User $user): Ticket
    {
        $this->assertStatus($ticket, [TicketStatus::Called]);

        return $this->announce($ticket, $user, false);
    }

    private function ensureReadyToCall(Unit $unit, User $user): void
    {
        if (! $user->location_id || ! $user->location_number) {
            throw new TicketException('Defina o local de atendimento antes de chamar senhas.');
        }

        if ($this->currentTicket($unit, $user)) {
            throw new TicketException('Finalize o atendimento atual antes de chamar outra senha.');
        }

        if ($unit->feature('pauses_enabled') && $user->openPause($unit)) {
            throw new TicketException('Você está em pausa. Retome o atendimento para chamar senhas.');
        }
    }

    /** Update atômico: só um atendente consegue pegar a senha. */
    private function claim(Ticket $ticket, User $user): bool
    {
        $now = now();

        return Ticket::whereKey($ticket->id)
            ->where('status', TicketStatus::Issued)
            ->whereNull('archived_at')
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))
            ->update([
                'status' => TicketStatus::Called,
                'user_id' => $user->id,
                'location_id' => $user->location_id,
                'location_number' => $user->location_number,
                'called_at' => $now,
                'wait_time' => max(0, (int) $ticket->arrived_at->diffInSeconds($now)),
                'updated_at' => $now,
            ]) === 1;
    }

    /** Registra a chamada no painel e avisa as telas. */
    private function announce(Ticket $ticket, User $user, bool $firstCall): Ticket
    {
        $ticket->loadMissing(['priority', 'customer', 'location', 'unit']);

        $message = UnitService::where('unit_id', $ticket->unit_id)
            ->where('service_id', $ticket->service_id)
            ->value('message');

        $call = PanelCall::create([
            'unit_id' => $ticket->unit_id,
            'service_id' => $ticket->service_id,
            'ticket_id' => $ticket->id,
            'prefix' => $ticket->prefix,
            'number' => $ticket->number,
            'message' => $message ?? '',
            'location' => $ticket->location?->name ?? '',
            'location_number' => $ticket->location_number ?? 0,
            'priority_weight' => $ticket->priority->weight,
            'priority_name' => $ticket->priority->name,
            'priority_color' => $ticket->priority->color,
            // o painel é público: nome conforme a política de privacidade e sem documento
            'customer_name' => Privacy::panelName($ticket->customer?->name),
            'customer_document' => null,
        ]);

        if ($firstCall) {
            $this->queue->registerCall($ticket, $user);
        }

        Realtime::broadcast(new TicketCalled($call));
        $this->dispatch(WebhookEvent::TicketCalled, $ticket);

        return $ticket;
    }

    // ------------------------------------------------------------------ atendimento

    public function start(Ticket $ticket, User $user): Ticket
    {
        $this->assertStatus($ticket, [TicketStatus::Called]);

        $now = now();
        $ticket->update([
            'status' => TicketStatus::Started,
            'user_id' => $user->id,
            'started_at' => $now,
            'travel_time' => (int) $ticket->called_at->diffInSeconds($now),
        ]);

        $this->dispatch(WebhookEvent::TicketStarted, $ticket);

        return $ticket;
    }

    public function noShow(Ticket $ticket, User $user): Ticket
    {
        $this->assertStatus($ticket, [TicketStatus::Called]);

        $now = now();
        $ticket->update([
            'status' => TicketStatus::NoShow,
            'user_id' => $user->id,
            'finished_at' => $now,
            'travel_time' => 0,
            'service_time' => 0,
            'total_time' => (int) $ticket->arrived_at->diffInSeconds($now),
        ]);

        $this->dispatch(WebhookEvent::TicketNoShow, $ticket);

        return $ticket;
    }

    /**
     * Encerra o atendimento registrando os serviços realizados.
     * Opcionalmente redireciona o cliente para outro serviço (nova senha com o mesmo número).
     */
    public function finish(
        Ticket $ticket,
        User $user,
        array $performedServiceIds,
        ?Resolution $resolution = null,
        ?string $notes = null,
        ?int $redirectServiceId = null,
        ?int $redirectUserId = null,
    ): Ticket {
        $this->assertStatus($ticket, [TicketStatus::Started]);

        $performedServiceIds = array_values(array_unique(array_map('intval', $performedServiceIds)));
        if (empty($performedServiceIds)) {
            throw new TicketException('Informe ao menos um serviço realizado.');
        }
        if (Service::whereIn('id', $performedServiceIds)->count() !== count($performedServiceIds)) {
            throw new TicketException('Serviço realizado inválido.');
        }

        $child = DB::transaction(function () use ($ticket, $performedServiceIds, $resolution, $notes, $redirectServiceId, $redirectUserId) {
            $now = now();
            $ticket->update([
                'status' => TicketStatus::Finished,
                'finished_at' => $now,
                'resolution' => $resolution,
                'notes' => $notes,
                'service_time' => (int) $ticket->started_at->diffInSeconds($now),
                'total_time' => (int) $ticket->arrived_at->diffInSeconds($now),
            ]);

            $ticket->performedServices()->sync(array_fill_keys($performedServiceIds, ['weight' => 1]));

            return $redirectServiceId ? $this->createRedirect($ticket, $redirectServiceId, $redirectUserId) : null;
        });

        $this->dispatch(WebhookEvent::TicketFinished, $ticket);
        if ($child) {
            $this->dispatch(WebhookEvent::TicketRedirected, $child, [$ticket->toApiArray(), $child->toApiArray()]);
        }

        return $ticket;
    }

    /** Erro de triagem: encaminha para o serviço correto mantendo o número da senha. */
    public function redirect(Ticket $ticket, User $user, int $serviceId, ?int $userId = null): Ticket
    {
        $this->assertStatus($ticket, [TicketStatus::Started, TicketStatus::Finished]);

        $child = DB::transaction(function () use ($ticket, $serviceId, $userId) {
            $now = now();
            $ticket->update([
                'status' => TicketStatus::Redirected,
                'finished_at' => $now,
                'service_time' => 0,
                'total_time' => (int) $ticket->arrived_at->diffInSeconds($now),
            ]);

            return $this->createRedirect($ticket, $serviceId, $userId);
        });

        $this->dispatch(WebhookEvent::TicketRedirected, $child, [$ticket->toApiArray(), $child->toApiArray()]);

        return $child;
    }

    private function createRedirect(Ticket $ticket, int $serviceId, ?int $userId): Ticket
    {
        $available = UnitService::where('unit_id', $ticket->unit_id)
            ->where('service_id', $serviceId)
            ->where('active', true)
            ->exists();

        if (! $available) {
            throw new TicketException('Serviço de destino indisponível nesta unidade.');
        }

        return Ticket::create([
            'unit_id' => $ticket->unit_id,
            'service_id' => $serviceId,
            'priority_id' => $ticket->priority_id,
            'customer_id' => $ticket->customer_id,
            'parent_id' => $ticket->id,
            'triage_user_id' => $ticket->user_id,
            'user_id' => $userId,
            'prefix' => $ticket->prefix,
            'number' => $ticket->number,
            'status' => TicketStatus::Issued,
            'arrived_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------ monitor

    public function cancel(Ticket $ticket): Ticket
    {
        if ($ticket->finished_at) {
            throw new TicketException('Esta senha já foi finalizada.');
        }

        $now = now();
        $ticket->update([
            'status' => TicketStatus::Cancelled,
            'finished_at' => $now,
            'total_time' => (int) $ticket->arrived_at->diffInSeconds($now),
            'service_time' => $ticket->started_at ? (int) $ticket->started_at->diffInSeconds($now) : null,
        ]);

        $this->dispatch(WebhookEvent::TicketCancelled, $ticket);

        return $ticket;
    }

    public function reactivate(Ticket $ticket): Ticket
    {
        $this->assertStatus($ticket, [TicketStatus::Cancelled, TicketStatus::NoShow]);

        $ticket->update([
            'status' => TicketStatus::Issued,
            'finished_at' => null,
            'user_id' => null,
        ]);

        $this->dispatch(WebhookEvent::TicketReactivated, $ticket);

        return $ticket;
    }

    public function transfer(Ticket $ticket, int $serviceId, Priority $priority): Ticket
    {
        if ($ticket->finished_at) {
            throw new TicketException('Esta senha já foi finalizada.');
        }

        $available = UnitService::where('unit_id', $ticket->unit_id)->where('service_id', $serviceId)->where('active', true)->exists();
        if (! $available) {
            throw new TicketException('Serviço indisponível nesta unidade.');
        }

        $ticket->update(['service_id' => $serviceId, 'priority_id' => $priority->id]);

        $this->dispatch(WebhookEvent::TicketTransferred, $ticket);

        return $ticket;
    }

    // ------------------------------------------------------------------ rotinas

    /**
     * Encerra o período: arquiva as senhas, limpa os painéis e reinicia os contadores.
     * Com $keepToday, mantém as senhas emitidas hoje (fuso da unidade).
     */
    public function archive(?Unit $unit = null, bool $keepToday = false): int
    {
        $units = $unit ? collect([$unit]) : Unit::all();
        $total = 0;

        foreach ($units as $u) {
            $cutoff = $keepToday
                ? CarbonImmutable::now($u->timezone())->startOfDay()->utc()
                : now()->addSecond();

            DB::transaction(function () use ($u, $cutoff, &$total) {
                $total += Ticket::current()
                    ->where('unit_id', $u->id)
                    ->where('arrived_at', '<', $cutoff)
                    ->update(['archived_at' => now(), 'notify_phone' => null]); // telefone só serve durante a espera

                PanelCall::where('unit_id', $u->id)->where('created_at', '<', $cutoff)->delete();

                if (! Ticket::current()->where('unit_id', $u->id)->exists()) {
                    UnitService::where('unit_id', $u->id)->update(['next_number' => DB::raw('start_number')]);
                }
            });

            Realtime::broadcast(new QueueUpdated($u->id));
        }

        Audit::log('data.archived', ($unit ? "Senhas reiniciadas na unidade {$unit->name}" : 'Senhas reiniciadas em todas as unidades')." ({$total} arquivada(s))", unitId: $unit?->id);

        return $total;
    }

    /** Apaga todo o histórico de atendimentos (irreversível). */
    public function clear(?Unit $unit = null): void
    {
        DB::transaction(function () use ($unit) {
            $scope = fn ($q) => $unit ? $q->where('unit_id', $unit->id) : $q;

            $scope(Ticket::query())->whereNotNull('parent_id')->update(['parent_id' => null]);
            $scope(PanelCall::query())->delete();
            $scope(Ticket::query())->delete();
            $scope(UnitService::query())->update(['next_number' => DB::raw('start_number')]);
        });

        Audit::log('data.cleared', $unit ? "Atendimentos apagados na unidade {$unit->name}" : 'Atendimentos apagados em todas as unidades', unitId: $unit?->id);

        foreach ($unit ? [$unit] : Unit::all() as $u) {
            Realtime::broadcast(new QueueUpdated($u->id));
        }
    }

    // ------------------------------------------------------------------ helpers

    private function audit(WebhookEvent $event, Ticket $ticket): void
    {
        [$action, $verb] = match ($event) {
            WebhookEvent::TicketCreated => ['ticket.issued', 'emitida'],
            WebhookEvent::TicketCalled => ['ticket.called', 'chamada'],
            WebhookEvent::TicketStarted => ['ticket.started', 'em atendimento'],
            WebhookEvent::TicketFinished => ['ticket.finished', 'encerrada'],
            WebhookEvent::TicketNoShow => ['ticket.no_show', 'marcada como não compareceu'],
            WebhookEvent::TicketCancelled => ['ticket.cancelled', 'cancelada'],
            WebhookEvent::TicketReactivated => ['ticket.reactivated', 'reativada'],
            WebhookEvent::TicketTransferred => ['ticket.transferred', 'transferida'],
            WebhookEvent::TicketRedirected => ['ticket.redirected', 'redirecionada'],
        };

        $details = match ($event) {
            WebhookEvent::TicketTransferred => ' para '.$ticket->service->name.' / '.$ticket->priority->name,
            WebhookEvent::TicketRedirected => ' para '.$ticket->service->name,
            default => '',
        };

        Audit::log($action, "Senha {$ticket->code()} {$verb}{$details}", $ticket, unitId: $ticket->unit_id);
    }

    /** Avisos por WhatsApp/SMS e pesquisa de satisfação (cada job confere as opções da unidade). */
    private function notifyCustomer(WebhookEvent $event, Ticket $ticket): void
    {
        $unit = $ticket->unit;
        $notify = $unit->feature('notify_enabled');
        $phone = (bool) $ticket->notify_phone;

        if ($event === WebhookEvent::TicketFinished && $phone && $unit->feature('survey_via_message')) {
            SendTicketNotification::dispatch($ticket->id, 'survey')->afterCommit();
        }

        if (! $notify) {
            return;
        }

        if ($event === WebhookEvent::TicketCreated && $phone) {
            SendTicketNotification::dispatch($ticket->id, 'issued')->afterCommit();
        }

        if ($event === WebhookEvent::TicketCalled && $phone) {
            SendTicketNotification::dispatch($ticket->id, 'called')->afterCommit();
        }

        // a fila andou: avisa quem ficou entre os próximos
        if (in_array($event, [WebhookEvent::TicketCalled, WebhookEvent::TicketTransferred, WebhookEvent::TicketCancelled], true)) {
            NotifyNearTickets::dispatch($ticket->unit_id)->afterCommit();
        }
    }

    private function assertStatus(Ticket $ticket, array $allowed): void
    {
        if (! in_array($ticket->status, $allowed, true)) {
            throw new TicketException("Ação não permitida: a senha {$ticket->code()} está com status \"{$ticket->status->label()}\".");
        }
    }

    private function dispatch(WebhookEvent $event, Ticket $ticket, ?array $payload = null): void
    {
        Realtime::broadcast(new QueueUpdated($ticket->unit_id, $ticket->id));
        $this->notifyCustomer($event, $ticket);
        $this->audit($event, $ticket);

        $webhooks = Webhook::where('enabled', true)->get()
            ->filter(fn (Webhook $w) => in_array($event->value, $w->events ?? [], true));

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload ??= $ticket->toApiArray();
        foreach ($webhooks as $webhook) {
            SendWebhook::dispatch($webhook->id, $event->value, $payload)->afterCommit();
        }
    }
}
