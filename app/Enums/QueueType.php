<?php

namespace App\Enums;

enum QueueType: string
{
    case All = 'all';
    case Normal = 'normal';
    case Priority = 'priority';
    case Appointment = 'appointment';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Todos',
            self::Normal => 'Normal',
            self::Priority => 'Prioridade',
            self::Appointment => 'Agendamento',
        };
    }
}
