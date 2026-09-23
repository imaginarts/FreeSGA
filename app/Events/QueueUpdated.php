<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Aviso leve para as telas recarregarem a fila da unidade. */
class QueueUpdated implements ShouldBroadcastNow
{
    public function __construct(public int $unitId, public ?int $ticketId = null) {}

    public function broadcastOn(): Channel
    {
        return new Channel("unit.{$this->unitId}");
    }

    public function broadcastAs(): string
    {
        return 'queue.updated';
    }
}
