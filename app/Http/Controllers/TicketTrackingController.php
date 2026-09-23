<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\PanelCall;
use App\Models\Ticket;
use App\Services\WaitEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/** Página pública em que o cliente acompanha a própria senha pelo celular. */
class TicketTrackingController extends Controller
{
    public function show(Ticket $ticket, string $token): View
    {
        $this->authorizeToken($ticket, $token);

        return view('track', [
            'ticket' => $ticket->load(['unit', 'service']),
            'unit' => $ticket->unit,
            'token' => $token,
        ]);
    }

    public function data(Ticket $ticket, string $token, WaitEstimator $estimator): JsonResponse
    {
        $this->authorizeToken($ticket, $token);

        // em caso de redirecionamento, acompanha a nova senha (mesmo número)
        $current = $ticket;
        while ($current->status === TicketStatus::Redirected && ($child = $current->children()->latest('id')->first())) {
            $current = $child;
        }
        $current->load(['unit', 'service', 'location']);
        $unit = $current->unit;

        $waiting = $current->status === TicketStatus::Issued && ! $current->archived_at;
        $needsPosition = $waiting && ($unit->feature('mobile_show_position') || $unit->feature('mobile_near_alert') || $unit->feature('mobile_show_eta'));
        $position = $needsPosition ? $estimator->position($current) : null;
        $eta = $waiting && $unit->feature('mobile_show_eta') ? $estimator->forTicket($current, $position) : null;
        $threshold = (int) $unit->setting('mobile_near_threshold');

        return response()->json([
            'code' => $current->code(),
            'service' => $current->service->name,
            'status' => $current->archived_at && $waiting ? 'expired' : $current->status->value,
            'status_label' => $current->status->label(),
            'position' => $unit->feature('mobile_show_position') ? $position : null,
            'eta' => $eta,
            'eta_text' => $waiting && $unit->feature('mobile_show_eta') ? WaitEstimator::format($eta) : null,
            'near' => $unit->feature('mobile_near_alert') && $position !== null && $position <= $threshold,
            'location' => $current->location ? $current->location->name.' '.str_pad((string) $current->location_number, 2, '0', STR_PAD_LEFT) : null,
            'called_at' => $current->called_at?->toIso8601String(),
            'survey_url' => $current->status === TicketStatus::Finished
                && $unit->feature('survey_on_tracking')
                && ! $current->surveyResponse()->exists()
                && $current->finished_at->gt(now()->subDays(SurveyController::VALID_DAYS))
                    ? $current->surveyUrl()
                    : null,
            'last_calls' => PanelCall::where('unit_id', $unit->id)
                ->where('service_id', $current->service_id)
                ->latest('id')->limit(10)->get()
                ->map(fn ($c) => ['code' => $c->code(), 'location' => $c->location.' '.str_pad((string) $c->location_number, 2, '0', STR_PAD_LEFT)])
                ->unique(fn ($c) => $c['code'].$c['location']) // rechamadas não repetem
                ->take(3)
                ->values(),
        ]);
    }

    private function authorizeToken(Ticket $ticket, string $token): void
    {
        abort_unless(hash_equals($ticket->trackingToken(), $token), 404);
        abort_unless($ticket->unit->feature('mobile_ticket'), 404, 'Acompanhamento pelo celular desabilitado nesta unidade.');
    }
}
