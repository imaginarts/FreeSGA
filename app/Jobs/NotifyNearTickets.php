<?php

namespace App\Jobs;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketNotification;
use App\Models\Unit;
use App\Services\QueueService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Após cada chamada, avisa quem está entre as próximas N senhas do seu serviço. */
class NotifyNearTickets implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $unitId) {}

    public function handle(QueueService $queue): void
    {
        $unit = Unit::find($this->unitId);
        if (! $unit?->feature('notify_near')) {
            return;
        }

        $threshold = max(1, (int) $unit->setting('notify_near_threshold'));

        $pending = Ticket::current()
            ->where('unit_id', $unit->id)
            ->where('status', TicketStatus::Issued)
            ->whereNotNull('notify_phone')
            ->whereNotIn('id', TicketNotification::select('ticket_id')->where('type', 'near')->where('status', 'sent'))
            ->pluck('service_id', 'id');

        foreach ($pending->unique() as $serviceId) {
            $ordered = $queue->unitQueue($unit, $serviceId)->pluck('id')->values();

            foreach ($pending->filter(fn ($s) => $s === $serviceId)->keys() as $ticketId) {
                $position = $ordered->search($ticketId);
                if ($position !== false && $position + 1 <= $threshold) {
                    SendTicketNotification::dispatchSync($ticketId, 'near', ['position' => $position + 1]);
                }
            }
        }
    }
}
