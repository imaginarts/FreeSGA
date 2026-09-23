<?php

namespace App\Models;

use App\Enums\ServiceType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Configuração de um serviço na unidade, incluindo o contador de senhas. */
class UnitService extends Model
{
    use Auditable;

    protected string $auditLabel = 'Serviço da unidade';

    protected $guarded = ['id'];

    protected $attributes = ['active' => false, 'type' => 1, 'weight' => 1, 'increment' => 1, 'start_number' => 1, 'next_number' => 1, 'message' => ''];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'type' => ServiceType::class,
            'weight' => 'integer',
            'increment' => 'integer',
            'start_number' => 'integer',
            'end_number' => 'integer',
            'max_tickets' => 'integer',
            'wait_target' => 'integer',
            'next_number' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('unit_services.active', true)
            ->whereHas('service', fn ($q) => $q->where('active', true));
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function resetCounter(): void
    {
        $this->update(['next_number' => $this->start_number]);
    }
}
