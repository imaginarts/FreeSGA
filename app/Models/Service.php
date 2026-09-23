<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use Auditable;

    protected string $auditLabel = 'Serviço';

    use SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = ['active' => true, 'weight' => 1];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'weight' => 'integer'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function scopeMain(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Service::class, 'parent_id')->orderBy('name');
    }

    public function unitServices(): HasMany
    {
        return $this->hasMany(UnitService::class);
    }

    /** Gera sigla estilo coluna de planilha: 1 => A, 27 => AA. */
    public static function prefixFor(int $n): string
    {
        $prefix = '';
        while ($n > 0) {
            $n--;
            $prefix = chr(65 + $n % 26).$prefix;
            $n = intdiv($n, 26);
        }

        return $prefix;
    }
}
