<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma chamada exibida no painel (cada chamada ou rechamada gera uma linha). */
class PanelCall extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function code(): string
    {
        return $this->prefix.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }

    public function toPanelArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code(),
            'prefix' => $this->prefix,
            'number' => $this->number,
            'message' => $this->message,
            'location' => $this->location,
            'location_number' => $this->location_number,
            'priority' => $this->priority_weight > 0,
            'priority_name' => $this->priority_name,
            'priority_color' => $this->priority_color,
            'customer_name' => $this->customer_name,
            'service' => $this->service?->name,
        ];
    }
}
