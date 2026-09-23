<?php

namespace App\Support;

/** Telefones no formato internacional (E.164 sem "+"), com padrão Brasil. */
class Phone
{
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        // DDD + número (10 ou 11 dígitos) => adiciona código do Brasil
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55'.$digits;
        }

        return strlen($digits) >= 12 && strlen($digits) <= 15 ? $digits : null;
    }

    public static function valid(?string $value): bool
    {
        return self::normalize($value) !== null;
    }

    /** Exibição mascarada, ex.: (11) 9****-4321. */
    public static function masked(?string $value): string
    {
        $d = (string) $value;
        if (str_starts_with($d, '55') && strlen($d) >= 12) {
            $local = substr($d, 4);

            return '('.substr($d, 2, 2).') '.substr($local, 0, 1).str_repeat('*', max(0, strlen($local) - 5)).'-'.substr($local, -4);
        }

        return str_repeat('*', max(0, strlen($d) - 4)).substr($d, -4);
    }
}
