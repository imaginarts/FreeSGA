<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Unit;

/**
 * Estima o tempo de espera a partir do ritmo real de chamadas do serviço.
 * Sem chamadas recentes, usa a duração média dos atendimentos dividida pelos atendentes ativos.
 */
class WaitEstimator
{
    /** @var array<string, ?float> */
    private array $intervals = [];

    public function __construct(private QueueService $queue) {}

    /** Segundos, em média, entre uma chamada e outra no serviço. Null quando não há dados. */
    public function interval(Unit $unit, int $serviceId): ?float
    {
        return $this->intervals["{$unit->id}:{$serviceId}"] ??= $this->computeInterval($unit, $serviceId);
    }

    private function computeInterval(Unit $unit, int $serviceId): ?float
    {
        $since = now()->subMinutes(max(5, (int) $unit->setting('eta_window')));

        $calls = Ticket::where('unit_id', $unit->id)
            ->where('service_id', $serviceId)
            ->where('called_at', '>=', $since)
            ->selectRaw('count(*) as total, min(called_at) as first_call, count(distinct user_id) as attendants')
            ->first();

        if ($calls->total >= 2) {
            $span = now()->diffInSeconds($calls->first_call, true);

            return max(30, $span / $calls->total);
        }

        $avgService = Ticket::where('unit_id', $unit->id)
            ->where('service_id', $serviceId)
            ->where('status', TicketStatus::Finished)
            ->where('finished_at', '>=', now()->subDays(7))
            ->avg('service_time');

        if (! $avgService) {
            return null;
        }

        $attendants = Ticket::where('unit_id', $unit->id)
            ->where('service_id', $serviceId)
            ->whereIn('status', TicketStatus::inProgress())
            ->distinct()
            ->count('user_id');

        return max(30, $avgService / max(1, $attendants, (int) $calls->attendants));
    }

    /** Posição da senha na fila do seu serviço (1 = próxima). */
    public function position(Ticket $ticket): ?int
    {
        if ($ticket->status !== TicketStatus::Issued || $ticket->archived_at) {
            return null;
        }

        $index = $this->queue->unitQueue($ticket->unit, $ticket->service_id)->search(fn ($t) => $t->id === $ticket->id);

        return $index === false ? null : $index + 1;
    }

    /** Segundos estimados até a senha ser chamada. */
    public function forTicket(Ticket $ticket, ?int $position = null): ?int
    {
        $position ??= $this->position($ticket);
        $interval = $position ? $this->interval($ticket->unit, $ticket->service_id) : null;

        return $interval ? (int) round($position * $interval) : null;
    }

    /** Estimativa para quem pegar uma senha agora em cada serviço: [service_id => [waiting, eta]]. */
    public function forServices(Unit $unit, array $serviceIds): array
    {
        $waiting = Ticket::current()
            ->where('unit_id', $unit->id)
            ->where('status', TicketStatus::Issued)
            ->whereIn('service_id', $serviceIds)
            ->selectRaw('service_id, count(*) as total')
            ->groupBy('service_id')
            ->pluck('total', 'service_id');

        $result = [];
        foreach ($serviceIds as $id) {
            $count = (int) ($waiting[$id] ?? 0);
            $interval = $this->interval($unit, $id);
            $result[$id] = [
                'waiting' => $count,
                'eta' => $count === 0 ? 0 : ($interval ? (int) round(($count + 1) * $interval) : null),
            ];
        }

        return $result;
    }

    public static function format(?int $seconds): string
    {
        if ($seconds === null) {
            return 'calculando…';
        }
        if ($seconds < 60) {
            return 'menos de 1 min';
        }

        $minutes = (int) round($seconds / 60);
        if ($minutes < 60) {
            return "~{$minutes} min";
        }

        return sprintf('~%dh%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
