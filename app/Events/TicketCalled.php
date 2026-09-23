<?php

namespace App\Events;

use App\Models\PanelCall;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Chamada de senha enviada aos painéis da unidade. */
class TicketCalled implements ShouldBroadcastNow
{
    public function __construct(public PanelCall $call) {}

    public function broadcastOn(): Channel
    {
        return new Channel("unit.{$this->call->unit_id}.panel");
    }

    public function broadcastAs(): string
    {
        return 'ticket.called';
    }

    public function broadcastWith(): array
    {
        return ['service_id' => $this->call->service_id, 'call' => $this->call->toPanelArray()];
    }
}
