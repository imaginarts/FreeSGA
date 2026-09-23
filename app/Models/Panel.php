<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/** Painel de chamadas (TV), acessado publicamente por public_id. */
class Panel extends Model
{
    use Auditable;

    protected string $auditLabel = 'Painel';

    protected $guarded = ['id'];

    public const DEFAULT_SETTINGS = [
        'highlight_bg' => '#0c4a6e',
        'history_bg' => '#0f172a',
        'footer_bg' => '#082f49',
        'voice' => true,
        'sound' => true,
        'footer_text' => '',
        'show_eta' => false, // tempo estimado de espera por serviço
    ];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (Panel $panel) {
            $panel->public_id ??= (string) Str::uuid7();
        });
    }

    public function setting(string $key): mixed
    {
        return ($this->settings ?? [])[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'panel_service');
    }
}
