<?php

namespace App\Jobs;

use App\Exceptions\TicketException;
use App\Models\Ticket;
use App\Models\TicketNotification;
use App\Services\MessagingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/** Envia um aviso (WhatsApp/SMS) sobre a senha ao telefone informado pelo cliente. */
class SendTicketNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Opção da unidade que habilita cada tipo de aviso. */
    private const FEATURES = [
        'issued' => 'notify_on_issue',
        'near' => 'notify_near',
        'called' => 'notify_on_call',
        'survey' => 'survey_via_message',
    ];

    public function __construct(public int $ticketId, public string $type, public array $extra = []) {}

    public function handle(MessagingService $messaging): void
    {
        $ticket = Ticket::with(['unit', 'service', 'location'])->find($this->ticketId);
        $unit = $ticket?->unit;

        if (! $ticket?->notify_phone || ! $unit->feature(self::FEATURES[$this->type])) {
            return;
        }
        if ($this->type !== 'survey' && ! $unit->feature('notify_enabled')) {
            return;
        }
        if (! $messaging->configured()) {
            return;
        }

        // cada tipo de aviso é enviado uma única vez por senha
        if (TicketNotification::where('ticket_id', $ticket->id)->where('type', $this->type)->where('status', 'sent')->exists()) {
            return;
        }

        $link = match ($this->type) {
            'issued' => $unit->feature('mobile_ticket') ? $ticket->trackingUrl() : '',
            'survey' => $ticket->surveyUrl(),
            default => '',
        };

        $vars = [
            'senha' => $ticket->code(),
            'servico' => $ticket->service->name,
            'unidade' => $unit->name,
            'local' => $ticket->location ? $ticket->location->name.' '.str_pad((string) $ticket->location_number, 2, '0', STR_PAD_LEFT) : '',
            'posicao' => $this->extra['position'] ?? '',
            'link' => $link,
        ];

        // somente as variáveis usadas por cada aviso (define os parâmetros dos modelos do WhatsApp)
        $used = [
            'issued' => ['senha', 'servico', 'unidade', 'link'],
            'near' => ['senha', 'posicao'],
            'called' => ['senha', 'local'],
            'survey' => ['unidade', 'link'],
        ][$this->type];

        $record = ['ticket_id' => $ticket->id, 'type' => $this->type, 'provider' => $messaging->provider(), 'to' => $ticket->notify_phone];

        try {
            $messaging->send($ticket->notify_phone, $this->type, array_intersect_key($vars, array_flip($used)));
            TicketNotification::create([...$record, 'status' => 'sent']);
        } catch (TicketException $e) {
            TicketNotification::create([...$record, 'status' => 'failed', 'error' => Str::limit($e->getMessage(), 250)]);
        }
    }
}
