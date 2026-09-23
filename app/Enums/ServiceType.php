<?php

namespace App\Enums;

/** Tipos de senha aceitos por um serviço na unidade. */
enum ServiceType: int
{
    case All = 1;
    case NormalOnly = 2;
    case PriorityOnly = 3;

    public function label(): string
    {
        return match ($this) {
            self::All => 'Normal e prioridade',
            self::NormalOnly => 'Somente normal',
            self::PriorityOnly => 'Somente prioridade',
        };
    }

    public function accepts(int $priorityWeight): bool
    {
        return match ($this) {
            self::All => true,
            self::NormalOnly => $priorityWeight === 0,
            self::PriorityOnly => $priorityWeight > 0,
        };
    }
}
