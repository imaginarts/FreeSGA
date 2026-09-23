<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\UnitService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintController extends Controller
{
    public function show(Request $request, Ticket $ticket): View
    {
        abort_unless($ticket->unit_id === $request->user()->current_unit_id, 404);

        return $this->render($request, $ticket);
    }

    /** Impressão sem login, autorizada pelo hash da senha (totens/API). */
    public function public(Request $request, Ticket $ticket): View
    {
        $hash = (string) ($request->query('hash') ?? $request->header('X-Hash'));
        abort_unless(hash_equals($ticket->hash(), $hash), 403);

        return $this->render($request, $ticket);
    }

    private function render(Request $request, Ticket $ticket): View
    {
        $ticket->load(['unit', 'service', 'priority']);

        return view('print', [
            'ticket' => $ticket,
            'unit' => $ticket->unit,
            'message' => UnitService::where('unit_id', $ticket->unit_id)->where('service_id', $ticket->service_id)->value('message'),
            'autoprint' => $request->boolean('autoprint'),
        ]);
    }
}
