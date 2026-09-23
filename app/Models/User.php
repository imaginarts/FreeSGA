<?php

namespace App\Models;

use App\Enums\Module;
use App\Enums\QueueType;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use Auditable;

    protected string $auditLabel = 'Usuário';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = ['active' => true, 'is_admin' => false, 'queue_type' => 'all'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'is_admin' => 'boolean',
            'queue_type' => QueueType::class,
            'behavior' => 'array',
        ];
    }

    public function fullName(): string
    {
        return trim($this->name.' '.$this->last_name);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function serviceUsers(): HasMany
    {
        return $this->hasMany(ServiceUser::class);
    }

    public function pauses(): HasMany
    {
        return $this->hasMany(AttendantPause::class);
    }

    /** Pausa em aberto do usuário na unidade. */
    public function openPause(Unit $unit): ?AttendantPause
    {
        return $this->pauses()->open()->where('unit_id', $unit->id)->latest('started_at')->first();
    }

    public function currentUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'current_unit_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Unidades que o usuário pode acessar. */
    public function availableUnits(): Collection
    {
        $query = Unit::where('active', true)->orderBy('name');

        if (! $this->is_admin) {
            $query->whereIn('id', $this->allocations()->select('unit_id'));
        }

        return $query->get();
    }

    public function allocationFor(?Unit $unit): ?Allocation
    {
        if (! $unit) {
            return null;
        }

        return $this->allocations()->with('role')->where('unit_id', $unit->id)->first();
    }

    public function canAccessModule(Module $module, ?Unit $unit = null): bool
    {
        if ($this->is_admin) {
            return true;
        }

        $unit ??= $this->currentUnit;
        if (! $unit) {
            return false;
        }

        $this->allocationCache[$unit->id] ??= $this->allocationFor($unit) ?? false;

        $allocation = $this->allocationCache[$unit->id];

        return $allocation && $allocation->role?->hasModule($module);
    }

    /** @var array<int, Allocation|false> */
    private array $allocationCache = [];

    /** Serviços ativos que o usuário atende na unidade. */
    public function servicesIn(Unit $unit): Collection
    {
        return $this->serviceUsers()
            ->where('unit_id', $unit->id)
            ->whereHas('service', fn ($q) => $q->where('active', true))
            ->with('service')
            ->get()
            ->sortBy('service.name')
            ->values();
    }

    /** Comportamento efetivo: preferência do usuário sobrepõe a global. */
    public function behavior(string $key): mixed
    {
        return ($this->behavior ?? [])[$key] ?? Setting::get('behavior')[$key] ?? null;
    }
}
