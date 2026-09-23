<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\SurveyResponse;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pesquisa de satisfação pública, respondida pelo cliente após o atendimento. */
class SurveyController extends Controller
{
    public const VALID_DAYS = 7;

    public function show(Ticket $ticket, string $token): View
    {
        $this->authorizeToken($ticket, $token);

        return view('survey', $this->viewData($ticket, $token));
    }

    public function store(Request $request, Ticket $ticket, string $token): RedirectResponse
    {
        $this->authorizeToken($ticket, $token);
        $state = $this->state($ticket);
        abort_unless($state === 'open', 422, 'Esta pesquisa não está disponível.');

        $unit = $ticket->unit;
        $scale = $unit->setting('survey_scale') === 'csat' ? 'csat' : 'nps';
        $data = $request->validate([
            'score' => ['required', 'integer', $scale === 'nps' ? 'between:0,10' : 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], ['score.required' => 'Escolha uma nota.']);

        SurveyResponse::create([
            'ticket_id' => $ticket->id,
            'unit_id' => $ticket->unit_id,
            'service_id' => $ticket->service_id,
            'user_id' => $ticket->user_id,
            'scale' => $scale,
            'score' => $data['score'],
            'comment' => $unit->feature('survey_comment') ? ($data['comment'] ?? null) : null,
        ]);

        return redirect()->route('survey.show', [$ticket, $token]);
    }

    /** open | answered | pending (atendimento não terminou) | expired */
    private function state(Ticket $ticket): string
    {
        if ($ticket->surveyResponse()->exists()) {
            return 'answered';
        }
        if ($ticket->status !== TicketStatus::Finished) {
            return 'pending';
        }

        return $ticket->finished_at->lt(now()->subDays(self::VALID_DAYS)) ? 'expired' : 'open';
    }

    private function viewData(Ticket $ticket, string $token): array
    {
        $ticket->load(['unit', 'service', 'user']);

        return [
            'ticket' => $ticket,
            'unit' => $ticket->unit,
            'token' => $token,
            'state' => $this->state($ticket),
            'scale' => $ticket->unit->setting('survey_scale') === 'csat' ? 'csat' : 'nps',
            'question' => $ticket->unit->setting('survey_question'),
            'askComment' => $ticket->unit->feature('survey_comment'),
        ];
    }

    private function authorizeToken(Ticket $ticket, string $token): void
    {
        abort_unless(hash_equals($ticket->surveyToken(), $token), 404);
        abort_unless($ticket->unit->feature('survey_enabled'), 404, 'Pesquisa desabilitada.');
    }
}
