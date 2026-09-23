<?php

namespace App\Services;

use App\Enums\QueueType;
use App\Enums\TicketStatus;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Monta e ordena as filas de senhas aguardando atendimento. */
class QueueService
{
    public const ORDERING_FIELDS = [
        'scheduled_at' => 'Data do agendamento',
        'arrived_at' => 'Data de chegada',
        'priority' => 'Peso da prioridade',
        'service_weight' => 'Peso do serviço',
        'user_service_weight' => 'Peso do serviço para o atendente',
        'unit_service_weight' => 'Peso do serviço na unidade',
        'balance' => 'Balanceamento (espera × prioridade)',
    ];

    /** Fila completa da unidade (todas as senhas emitidas). */
    public function unitQueue(Unit $unit, ?int $serviceId = null): Collection
    {
        $query = $this->baseQuery($unit);

        if ($serviceId) {
            $query->where('tickets.service_id', $serviceId);
        }

        $this->applyOrdering($query, $unit, null);

        return $query->get();
    }

    /** Fila do atendente: serviços que ele atende, filtrada pelo tipo de atendimento. */
    public function userQueue(Unit $unit, User $user, ?QueueType $type = null, ?array $serviceIds = null, ?int $limit = null): Collection
    {
        $serviceIds ??= $user->servicesIn($unit)->pluck('service_id')->all();

        if (empty($serviceIds)) {
            return collect();
        }

        $query = $this->baseQuery($unit)
            ->join('service_user', function ($join) use ($user, $unit) {
                $join->on('service_user.service_id', '=', 'tickets.service_id')
                    ->where('service_user.user_id', $user->id)
                    ->where('service_user.unit_id', $unit->id);
            })
            ->whereIn('tickets.service_id', $serviceIds)
            // senha redirecionada para um atendente específico só aparece para ele
            ->where(fn ($q) => $q->whereNull('tickets.user_id')->orWhere('tickets.user_id', $user->id));

        match ($type ?? QueueType::All) {
            QueueType::Normal => $query->where('priorities.weight', 0),
            QueueType::Priority => $query->where('priorities.weight', '>', 0),
            QueueType::Appointment => $query->whereNotNull('tickets.scheduled_at'),
            QueueType::All => null,
        };

        $this->applyOrdering($query, $unit, $user);

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    private function baseQuery(Unit $unit): Builder
    {
        return Ticket::query()
            ->select('tickets.*')
            ->with(['service', 'priority', 'customer'])
            ->join('priorities', 'priorities.id', '=', 'tickets.priority_id')
            ->join('services', 'services.id', '=', 'tickets.service_id')
            ->join('unit_services', function ($join) {
                $join->on('unit_services.service_id', '=', 'tickets.service_id')
                    ->on('unit_services.unit_id', '=', 'tickets.unit_id');
            })
            ->where('tickets.unit_id', $unit->id)
            ->where('tickets.status', TicketStatus::Issued)
            ->whereNull('tickets.archived_at');
    }

    private function applyOrdering(Builder $query, Unit $unit, ?User $user): void
    {
        $ignorePriority = $this->shouldIgnorePriority($unit, $user);

        foreach (Setting::get('queue')['ordering'] as $rule) {
            $direction = strtolower($rule['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

            match ($rule['field'] ?? null) {
                // agendados primeiro, depois pelo horário
                'scheduled_at' => $query->orderByRaw('tickets.scheduled_at is null')->orderBy('tickets.scheduled_at', $direction),
                'arrived_at' => $query->orderBy('tickets.arrived_at', $direction),
                'priority' => $ignorePriority ? null : $query->orderBy('priorities.weight', $direction),
                'service_weight' => $query->orderBy('services.weight', $direction),
                'user_service_weight' => $user ? $query->orderBy('service_user.weight', $direction) : null,
                'unit_service_weight' => $query->orderBy('unit_services.weight', $direction),
                'balance' => $ignorePriority ? null : $query->orderByRaw($this->balanceExpression().' '.$direction),
                default => null,
            };
        }

        $query->orderBy('tickets.id');
    }

    private function balanceExpression(): string
    {
        $wait = DB::connection()->getDriverName() === 'sqlite'
            ? "(strftime('%s','now') - strftime('%s', tickets.arrived_at))"
            : 'TIMESTAMPDIFF(SECOND, tickets.arrived_at, UTC_TIMESTAMP())';

        return "$wait * (priorities.weight + 1)";
    }

    /** Intercalação: após N prioridades seguidas, a próxima chamada ignora a prioridade. */
    public function shouldIgnorePriority(Unit $unit, ?User $user): bool
    {
        $behavior = Setting::get('behavior');

        if (! $behavior['priority_swap']) {
            return false;
        }

        $owner = $behavior['priority_swap_method'] === 'user' ? $user : $unit;
        if (! $owner) {
            return false;
        }

        $count = (int) $owner->newQuery()->whereKey($owner->getKey())->value('priority_swap_count');

        return $count >= (int) $behavior['priority_swap_count'];
    }

    public function registerCall(Ticket $ticket, User $user): void
    {
        $behavior = Setting::get('behavior');

        if (! $behavior['priority_swap']) {
            return;
        }

        $owner = $behavior['priority_swap_method'] === 'user' ? $user : $ticket->unit;
        $query = $owner->newQuery()->whereKey($owner->getKey());

        $ticket->priority->isPriority()
            ? $query->increment('priority_swap_count')
            : $query->update(['priority_swap_count' => 0]);
    }
}
