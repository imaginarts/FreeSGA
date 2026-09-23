<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Motivo de pausa do atendente (almoço, intervalo...). */
class PauseReason extends Model
{
    use Auditable;

    protected string $auditLabel = 'Motivo de pausa';

    protected $guarded = ['id'];

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'max_minutes' => 'integer'];
    }
}
