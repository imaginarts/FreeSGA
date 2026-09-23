<?php

namespace App\Models;

use App\Enums\Module;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Perfil: conjunto de módulos liberados. */
class Role extends Model
{
    use Auditable;

    protected string $auditLabel = 'Perfil';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['modules' => 'array'];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function hasModule(Module $module): bool
    {
        return in_array($module->value, $this->modules ?? [], true);
    }
}
