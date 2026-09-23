<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use Auditable;

    protected string $auditLabel = 'Unidade';

    use SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = ['active' => true];

    /** Recursos opcionais, habilitados por unidade em Configurações da unidade. */
    public const DEFAULT_SETTINGS = [
        'mobile_ticket' => true,          // QR na senha + página de acompanhamento
        'mobile_show_position' => true,
        'mobile_show_eta' => true,
        'mobile_near_alert' => true,
        'mobile_near_threshold' => 3,     // avisar quando faltarem N senhas
        'triage_show_qr' => true,         // QR na tela da triagem após emitir
        'triage_show_eta' => true,        // tempo estimado nos botões da triagem
        'eta_window' => 60,               // minutos de histórico usados no cálculo
        'sla_enabled' => true,            // metas de tempo de espera
        'sla_default_target' => 15,       // minutos, quando o serviço não tem meta própria
        'sla_warning_percent' => 80,      // amarelo a partir de X% da meta
        'sla_monitor_sound' => true,      // alerta sonoro no monitor ao estourar a meta
        'sla_attendance_highlight' => true, // cores de meta na fila do atendente
        'pauses_enabled' => true,         // pausas do atendente
        'pause_require_reason' => true,
        'pause_alert_exceeded' => true,   // destacar pausa acima do tempo máximo
        'direct_print_enabled' => true,   // impressoras térmicas ESC/POS
        'kiosk_enabled' => true,          // totens de autoatendimento
        'notify_enabled' => false,        // avisos por WhatsApp/SMS (requer provedor em Admin → Mensagens)
        'notify_on_issue' => true,
        'notify_near' => true,
        'notify_near_threshold' => 3,
        'notify_on_call' => true,
        'survey_enabled' => false,        // pesquisa de satisfação
        'survey_scale' => 'nps',          // nps (0-10) | csat (1-5)
        'survey_question' => 'De 0 a 10, o quanto você recomendaria nosso atendimento?',
        'survey_comment' => true,
        'survey_via_message' => true,     // enviar link por WhatsApp/SMS ao encerrar
        'survey_on_tracking' => true,     // mostrar na página de acompanhamento
    ];

    /** Opções que dependem de uma chave principal. */
    private const FEATURE_PARENTS = [
        'mobile_' => 'mobile_ticket',
        'sla_' => 'sla_enabled',
        'pause_' => 'pauses_enabled',
        'notify_' => 'notify_enabled',
        'survey_' => 'survey_enabled',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'active' => 'boolean',
            'print_show_date' => 'boolean',
            'print_show_priority' => 'boolean',
            'print_show_unit_name' => 'boolean',
            'print_show_service_name' => 'boolean',
            'print_show_service_message' => 'boolean',
        ];
    }

    public function setting(string $key): mixed
    {
        return ($this->settings ?? [])[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    /** Recurso ligado? Subopções do celular dependem da opção principal. */
    public function feature(string $key): bool
    {
        foreach (self::FEATURE_PARENTS as $prefix => $parent) {
            if (str_starts_with($key, $prefix) && $key !== $parent && ! $this->setting($parent)) {
                return false;
            }
        }
        if ($key === 'triage_show_qr' && ! $this->setting('mobile_ticket')) {
            return false;
        }

        return (bool) $this->setting($key);
    }

    public function timezone(): string
    {
        return $this->timezone ?: config('app.timezone');
    }

    public function unitServices(): HasMany
    {
        return $this->hasMany(UnitService::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'unit_services')->withPivot(['prefix', 'active']);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function panels(): HasMany
    {
        return $this->hasMany(Panel::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
