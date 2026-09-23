<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Issued = 'issued';
    case Called = 'called';
    case Started = 'started';
    case Finished = 'finished';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
    case Redirected = 'redirected';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Emitida',
            self::Called => 'Chamada',
            self::Started => 'Em atendimento',
            self::Finished => 'Encerrada',
            self::NoShow => 'Não compareceu',
            self::Cancelled => 'Cancelada',
            self::Redirected => 'Erro de triagem',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Issued => 'slate',
            self::Called => 'amber',
            self::Started => 'blue',
            self::Finished => 'emerald',
            self::NoShow => 'orange',
            self::Cancelled => 'red',
            self::Redirected => 'purple',
        };
    }

    public static function inProgress(): array
    {
        return [self::Called, self::Started];
    }
}
