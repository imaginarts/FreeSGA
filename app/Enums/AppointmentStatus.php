<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendado',
            self::Confirmed => 'Confirmado',
            self::NoShow => 'Não compareceu',
        };
    }
}
