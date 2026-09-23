<?php

namespace App\Enums;

enum Resolution: string
{
    case Resolved = 'resolved';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Resolved => 'Resolvido',
            self::Pending => 'Pendente',
        };
    }
}
