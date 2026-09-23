<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendantPause extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration' => 'integer',
            'max_minutes' => 'integer',
        ];
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function pauseReason(): BelongsTo
    {
        return $this->belongsTo(PauseReason::class);
    }

    public function elapsed(): int
    {
        return $this->duration ?? (int) $this->started_at->diffInSeconds(now(), true);
    }

    public function exceeded(): bool
    {
        return $this->max_minutes > 0 && $this->elapsed() > $this->max_minutes * 60;
    }
}
