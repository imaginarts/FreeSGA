<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Priority extends Model
{
    use Auditable;

    protected string $auditLabel = 'Prioridade';

    use SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = ['active' => true, 'weight' => 0];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'weight' => 'integer'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function isPriority(): bool
    {
        return $this->weight > 0;
    }
}
