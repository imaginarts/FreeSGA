<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Totem de autoatendimento para emissão de senhas. */
class Kiosk extends Model
{
    use Auditable;

    protected string $auditLabel = 'Totem';

    public const PRINT_MODES = [
        'browser' => 'Navegador (impressora do computador do totem)',
        'printer' => 'Impressora térmica cadastrada (direto pelo servidor)',
        'none' => 'Não imprimir (apenas QR code na tela)',
    ];

    public const DEFAULT_SETTINGS = [
        'welcome_title' => 'Bem-vindo!',
        'welcome_text' => 'Toque na tela para retirar sua senha',
        'ask_document' => false,        // pedir CPF antes de emitir
        'require_document' => false,    // CPF obrigatório (quando ask_document)
        'show_priority' => true,        // botão de atendimento preferencial
        'ask_phone' => false,           // oferecer aviso por WhatsApp (se a unidade tiver avisos ligados)
        'appointments' => true,         // "Tenho agendamento" (check-in por CPF)
        'show_eta' => true,             // espera estimada nos serviços
        'show_qr' => true,              // QR de acompanhamento na tela final
        'reset_seconds' => 12,          // volta ao início após emitir
        'idle_seconds' => 45,           // volta ao início por inatividade
        'high_contrast' => false,
        'primary_color' => '#0369a1',
    ];

    protected $guarded = ['id'];

    protected $attributes = ['print_mode' => 'browser', 'active' => true];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'services' => 'array',
            'active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Kiosk $kiosk) {
            $kiosk->public_id ??= (string) Str::uuid7();
        });
    }

    public function setting(string $key): mixed
    {
        return ($this->settings ?? [])[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }
}
