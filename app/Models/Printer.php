<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Impressora térmica ESC/POS (rede ou compartilhada no Windows). */
class Printer extends Model
{
    use Auditable;

    protected string $auditLabel = 'Impressora';

    public const CONNECTIONS = [
        'network' => 'Rede (IP:porta)',
        'windows' => 'Compartilhada no Windows (USB)',
    ];

    protected $guarded = ['id'];

    protected $attributes = [
        'connection_type' => 'network', 'port' => 9100, 'paper_width' => 80,
        'cut' => true, 'print_qr' => true, 'strip_accents' => false, 'active' => true,
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'paper_width' => 'integer',
            'cut' => 'boolean',
            'print_qr' => 'boolean',
            'strip_accents' => 'boolean',
            'active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Colunas de texto por linha em fonte normal. */
    public function columns(): int
    {
        return $this->paper_width <= 58 ? 32 : 48;
    }

    public function address(): string
    {
        return $this->connection_type === 'network' ? "{$this->host}:{$this->port}" : $this->host;
    }
}
