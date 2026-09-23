<?php

namespace App\Enums;

enum WebhookEvent: string
{
    case TicketCreated = 'ticket.created';
    case TicketCalled = 'ticket.called';
    case TicketStarted = 'ticket.started';
    case TicketFinished = 'ticket.finished';
    case TicketNoShow = 'ticket.no_show';
    case TicketCancelled = 'ticket.cancelled';
    case TicketReactivated = 'ticket.reactivated';
    case TicketRedirected = 'ticket.redirected';
    case TicketTransferred = 'ticket.transferred';

    public function label(): string
    {
        return match ($this) {
            self::TicketCreated => 'Senha emitida',
            self::TicketCalled => 'Senha chamada',
            self::TicketStarted => 'Atendimento iniciado',
            self::TicketFinished => 'Atendimento encerrado',
            self::TicketNoShow => 'Não compareceu',
            self::TicketCancelled => 'Senha cancelada',
            self::TicketReactivated => 'Senha reativada',
            self::TicketRedirected => 'Senha redirecionada',
            self::TicketTransferred => 'Senha transferida',
        };
    }
}
