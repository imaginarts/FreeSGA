<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Configurações globais em chave/valor (JSON). */
class Setting extends Model
{
    use Auditable;

    protected string $auditLabel = 'Configuração';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public const DEFAULTS = [
        'behavior' => [
            'priority_swap' => false,
            'priority_swap_method' => 'unit', // unit|user
            'priority_swap_count' => 1,
            'call_by_service' => false,
            'call_out_of_order' => false,
            'change_queue_type' => true,
            'appointment_delay' => 60, // minutos de tolerância para confirmar agendamento
        ],
        'queue' => [
            'ordering' => [
                ['field' => 'scheduled_at', 'order' => 'asc'],
                ['field' => 'user_service_weight', 'order' => 'asc'],
                ['field' => 'priority', 'order' => 'desc'],
                ['field' => 'unit_service_weight', 'order' => 'desc'],
                ['field' => 'arrived_at', 'order' => 'asc'],
            ],
        ],
        // Provedor de WhatsApp/SMS. Tokens e cabeçalhos ficam criptografados.
        'messaging' => [
            'provider' => 'none', // none | log | whatsapp_cloud | http
            'whatsapp_phone_number_id' => '',
            'whatsapp_token' => '',
            'whatsapp_api_version' => 'v21.0',
            'whatsapp_language' => 'pt_BR',
            'whatsapp_templates' => ['issued' => '', 'near' => '', 'called' => '', 'survey' => ''],
            'http_url' => '',
            'http_headers' => '',
            'http_body' => '{"phone": "{telefone}", "message": "{mensagem}"}',
            'texts' => [
                'issued' => 'Olá! Sua senha é {senha} ({servico}) na {unidade}. Acompanhe a fila: {link}',
                'near' => 'Sua vez está chegando! Senha {senha}: faltam {posicao} senha(s). Fique próximo ao local de atendimento.',
                'called' => 'Sua senha {senha} foi chamada! Dirija-se ao {local}.',
                'survey' => 'Obrigado por nos visitar! Como foi seu atendimento na {unidade}? Avalie em 10 segundos: {link}',
            ],
        ],
        'privacy' => [
            'panel_customer_name' => 'first',  // hidden | first | full (painel da TV e voz)
            'mask_document' => true,           // CPF mascarado nas telas de operação
            'public_attendant_name' => true,   // nome do atendente nas páginas públicas
            'phone_consent_text' => 'Usaremos seu número apenas para avisos sobre esta senha. Ele é apagado ao fim do dia.',
            'retention_enabled' => false,      // anonimização automática de clientes inativos
            'retention_months' => 24,
        ],
        'audit' => [
            'enabled' => true,
            'log_ticket_flow' => false,        // true: todo o fluxo; false: só ações sensíveis
            'log_logins' => true,
            'log_data_access' => true,         // exportação/anonimização/consulta de dados pessoais
            'retention_days' => 365,
        ],
        'appearance' => [
            'app_name' => 'FreeSGA',
            'primary_color' => '#0369a1',
        ],
    ];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key): array
    {
        return Cache::rememberForever("settings.$key", function () use ($key) {
            $stored = static::find($key)?->value ?? [];

            return array_replace(self::DEFAULTS[$key] ?? [], $stored);
        });
    }

    public static function put(string $key, array $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("settings.$key");
    }
}
