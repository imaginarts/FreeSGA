<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use Auditable;

    protected string $auditLabel = 'Webhook';

    protected $guarded = ['id'];

    protected $attributes = ['enabled' => true];

    protected function casts(): array
    {
        return ['headers' => 'array', 'events' => 'array', 'enabled' => 'boolean'];
    }
}
