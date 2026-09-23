<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use Auditable;

    protected string $auditLabel = 'Cliente';

    protected bool $auditValues = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
