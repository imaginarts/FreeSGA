<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Resposta positiva: 9-10 no NPS, 4-5 no CSAT. */
    public function positive(): bool
    {
        return $this->scale === 'nps' ? $this->score >= 9 : $this->score >= 4;
    }

    public function negative(): bool
    {
        return $this->scale === 'nps' ? $this->score <= 6 : $this->score <= 2;
    }
}
