<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    use Auditable;

    protected string $auditLabel = 'Agendamento';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'scheduled'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AppointmentStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }

    /** Data/hora do agendamento no fuso da unidade. */
    public function scheduledAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date->format('Y-m-d').' '.$this->time, $this->unit->timezone());
    }
}
