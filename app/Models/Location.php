<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Tipo de ponto de atendimento: Guichê, Mesa, Sala... */
class Location extends Model
{
    use Auditable;

    protected string $auditLabel = 'Local';

    protected $guarded = ['id'];
}
