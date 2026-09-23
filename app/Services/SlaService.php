<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Unit;
use App\Models\UnitService;

/** Metas de tempo de espera por serviço. */
class SlaService
{
    public const OK = 'ok';

    public const WARNING = 'warning';

    public const BREACH = 'breach';

    /** @var array<int, array<int, ?int>> unit_id => [service_id => segundos] */
    private array $targets = [];

    public function enabled(Unit $unit): bool
    {
        return $unit->feature('sla_enabled');
    }

    /** Meta em segundos do serviço na unidade (null = sem meta). */
    public function target(Unit $unit, int $serviceId): ?int
    {
        if (! $this->enabled($unit)) {
            return null;
        }

        $this->targets[$unit->id] ??= UnitService::where('unit_id', $unit->id)->pluck('wait_target', 'service_id')->all();
        $minutes = ($this->targets[$unit->id][$serviceId] ?? null) ?: (int) $unit->setting('sla_default_target');

        return $minutes > 0 ? $minutes * 60 : null;
    }

    /** Tempo de espera considerado: até a chamada, ou até agora se ainda aguarda. */
    public function waited(Ticket $ticket): int
    {
        return $ticket->wait_time ?? (int) $ticket->arrived_at->diffInSeconds(now(), true);
    }

    public function state(Ticket $ticket, ?Unit $unit = null): ?string
    {
        $unit ??= $ticket->unit;
        $target = $this->target($unit, $ticket->service_id);

        if (! $target) {
            return null;
        }

        $waited = $this->waited($ticket);
        $warning = $target * max(1, min(100, (int) $unit->setting('sla_warning_percent'))) / 100;

        return match (true) {
            $waited > $target => self::BREACH,
            $waited >= $warning => self::WARNING,
            default => self::OK,
        };
    }

    public static function classes(?string $state): string
    {
        return match ($state) {
            self::BREACH => 'ring-2 ring-red-500 bg-red-50 dark:bg-red-950/50',
            self::WARNING => 'ring-2 ring-amber-400 bg-amber-50 dark:bg-amber-950/40',
            default => '',
        };
    }
}
