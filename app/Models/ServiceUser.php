<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Serviço que o atendente atende numa unidade. */
class ServiceUser extends Model
{
    use Auditable;

    protected string $auditLabel = 'Serviço do atendente';

    protected $table = 'service_user';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['weight' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
