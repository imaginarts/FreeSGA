<?php

namespace App\Support;

class Cpf
{
    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value);
    }

    public static function valid(?string $value): bool
    {
        $cpf = self::digits($value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * ($t + 1 - $i);
            }
            if ((int) $cpf[$t] !== ((10 * $sum) % 11) % 10) {
                return false;
            }
        }

        return true;
    }

    public static function format(string $digits): string
    {
        return strlen($digits) === 11
            ? substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2)
            : $digits;
    }
}
