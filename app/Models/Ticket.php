<?php

namespace App\Models;

use App\Enums\Resolution;
use App\Enums\TicketStatus;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Senha / atendimento. */
class Ticket extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'resolution' => Resolution::class,
            'meta' => 'array',
            'scheduled_at' => 'datetime',
            'arrived_at' => 'datetime',
            'called_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** Senhas do período corrente (ainda não arquivadas). */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('tickets.archived_at');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function triageUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triage_user_id')->withTrashed();
    }

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Ticket::class, 'parent_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Serviços realizados (codificados) no atendimento. */
    public function performedServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'ticket_services')->withPivot('weight')->withTrashed();
    }

    public function code(): string
    {
        return $this->prefix.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }

    /** Token público usado para impressão sem login. */
    public function hash(): string
    {
        return sha1($this->id.':'.$this->arrived_at->getTimestamp());
    }

    /** Token curto usado no link de acompanhamento pelo celular. */
    public function trackingToken(): string
    {
        return substr($this->hash(), 0, 16);
    }

    public function trackingUrl(): string
    {
        return route('ticket.track', ['ticket' => $this->id, 'token' => $this->trackingToken()]);
    }

    public function surveyToken(): string
    {
        return substr(sha1('survey:'.$this->hash()), 0, 16);
    }

    public function surveyUrl(): string
    {
        return route('survey.show', ['ticket' => $this->id, 'token' => $this->surveyToken()]);
    }

    public function surveyResponse(): HasOne
    {
        return $this->hasOne(SurveyResponse::class);
    }

    /** QR code (SVG) que abre o acompanhamento da senha. */
    public function trackingQrSvg(int $size = 160): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($this->trackingUrl());
    }

    public function localTime(?\DateTimeInterface $date, string $format = 'd/m/Y H:i:s'): ?string
    {
        return $date?->copy()->setTimezone($this->unit->timezone())->format($format);
    }

    public static function formatSeconds(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    public function toApiArray(): array
    {
        $this->loadMissing(['unit', 'service', 'priority', 'user', 'triageUser', 'customer', 'location', 'performedServices']);

        $date = fn ($d) => $this->localTime($d, 'Y-m-d\TH:i:s');

        return [
            'id' => $this->id,
            'code' => $this->code(),
            'prefix' => $this->prefix,
            'number' => $this->number,
            'status' => $this->status->value,
            'resolution' => $this->resolution?->value,
            'notes' => $this->notes,
            'unit' => ['id' => $this->unit->id, 'name' => $this->unit->name],
            'service' => ['id' => $this->service->id, 'name' => $this->service->name],
            'priority' => [
                'id' => $this->priority->id,
                'name' => $this->priority->name,
                'weight' => $this->priority->weight,
                'color' => $this->priority->color,
            ],
            'location' => $this->location ? ['id' => $this->location->id, 'name' => $this->location->name] : null,
            'location_number' => $this->location_number,
            'customer' => $this->customer?->only(['id', 'name', 'document', 'email', 'phone']),
            'triage_user' => $this->triageUser?->only(['id', 'login']),
            'user' => $this->user?->only(['id', 'login']),
            'parent_id' => $this->parent_id,
            'scheduled_at' => $date($this->scheduled_at),
            'arrived_at' => $date($this->arrived_at),
            'called_at' => $date($this->called_at),
            'started_at' => $date($this->started_at),
            'finished_at' => $date($this->finished_at),
            'wait_time' => $this->wait_time,
            'service_time' => $this->service_time,
            'performed_services' => $this->performedServices->map->only(['id', 'name'])->values(),
            'meta' => $this->meta,
            'hash' => $this->hash(),
            'tracking_url' => $this->unit->feature('mobile_ticket') ? $this->trackingUrl() : null,
        ];
    }
}
