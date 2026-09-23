<?php

namespace App\Support;

use App\Models\Setting;

/** Exibição de dados pessoais conforme Admin → Privacidade e auditoria. */
class Privacy
{
    /** CPF/documento mascarado nas telas de operação: ***.982.247-** */
    public static function document(?string $document): ?string
    {
        if ($document === null || $document === '' || ! Setting::get('privacy')['mask_document']) {
            return $document;
        }

        $digits = preg_replace('/\D/', '', $document);
        if (strlen($digits) === 11) {
            return '***.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-**';
        }

        return str_repeat('*', max(0, mb_strlen($document) - 4)).mb_substr($document, -4);
    }

    /** Nome do cliente no painel da TV e na chamada por voz. */
    public static function panelName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return match (Setting::get('privacy')['panel_customer_name']) {
            'full' => $name,
            'first' => strtok(trim($name), ' ') ?: null,
            default => null,
        };
    }
}
