<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketNotification extends Model
{
    public const TYPES = [
        'issued' => 'Senha emitida',
        'near' => 'Sua vez está chegando',
        'called' => 'Senha chamada',
        'survey' => 'Pesquisa de satisfação',
    ];

    protected $guarded = ['id'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
