<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class Realtime
{
    /**
     * Transmite o evento sem derrubar a operação se o Reverb estiver fora do ar:
     * as telas também fazem polling como fallback.
     */
    public static function broadcast(object $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $e) {
            Log::warning('Falha ao transmitir evento em tempo real: '.$e->getMessage());
        }
    }
}
