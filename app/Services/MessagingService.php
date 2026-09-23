<?php

namespace App\Services;

use App\Exceptions\TicketException;
use App\Models\Setting;
use App\Support\Phone;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envio de mensagens por WhatsApp/SMS.
 *  - whatsapp_cloud: API oficial da Meta (mensagens por modelos aprovados)
 *  - http: qualquer provedor com API HTTP (Z-API, Evolution, Twilio, SMS...)
 *  - log: apenas registra no log (testes/homologação)
 */
class MessagingService
{
    public const PROVIDERS = [
        'none' => 'Desativado',
        'log' => 'Modo teste (apenas registra no log)',
        'whatsapp_cloud' => 'WhatsApp Cloud API (Meta, oficial)',
        'http' => 'HTTP genérico (Z-API, Evolution, Twilio, SMS…)',
    ];

    public const PLACEHOLDERS = ['{senha}', '{servico}', '{unidade}', '{local}', '{posicao}', '{link}'];

    public function config(): array
    {
        return Setting::get('messaging');
    }

    public function provider(): string
    {
        return $this->config()['provider'] ?? 'none';
    }

    public function configured(): bool
    {
        $c = $this->config();

        return match ($this->provider()) {
            'log' => true,
            'whatsapp_cloud' => $c['whatsapp_phone_number_id'] !== '' && $c['whatsapp_token'] !== '',
            'http' => $c['http_url'] !== '',
            default => false,
        };
    }

    /**
     * Envia a mensagem do tipo informado. $vars usa as chaves dos placeholders sem chaves: senha, servico...
     *
     * @throws TicketException quando o provedor recusa ou está mal configurado
     */
    public function send(string $to, string $type, array $vars): void
    {
        if (! $this->configured()) {
            throw new TicketException('Provedor de mensagens não configurado.');
        }

        $c = $this->config();
        $text = $this->render($c['texts'][$type] ?? '', $vars);

        match ($this->provider()) {
            'log' => Log::info("[SGA mensagem:{$type}] para ".Phone::masked($to).": {$text}"),
            'whatsapp_cloud' => $this->sendWhatsAppCloud($c, $to, $type, $text, $vars),
            'http' => $this->sendHttp($c, $to, $text),
        };
    }

    public function render(string $template, array $vars): string
    {
        $replace = [];
        foreach ($vars as $key => $value) {
            $replace['{'.$key.'}'] = (string) $value;
        }

        return strtr($template, $replace);
    }

    private function sendWhatsAppCloud(array $c, string $to, string $type, string $text, array $vars): void
    {
        $template = $c['whatsapp_templates'][$type] ?? '';
        $url = sprintf('https://graph.facebook.com/%s/%s/messages', $c['whatsapp_api_version'] ?: 'v21.0', $c['whatsapp_phone_number_id']);

        // Mensagens iniciadas pela empresa exigem modelo aprovado; os parâmetros seguem a ordem dos placeholders
        $payload = $template !== ''
            ? [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => $c['whatsapp_language'] ?: 'pt_BR'],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => array_map(
                            fn ($value) => ['type' => 'text', 'text' => (string) $value],
                            array_values(array_filter($this->orderedVars($vars), fn ($v) => $v !== null && $v !== '')),
                        ),
                    ]],
                ],
            ]
            : ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => ['body' => $text]];

        $response = Http::timeout(10)->withToken($this->secret($c['whatsapp_token']))->post($url, $payload);

        if ($response->failed()) {
            throw new TicketException('WhatsApp recusou a mensagem: '.($response->json('error.message') ?? 'HTTP '.$response->status()));
        }
    }

    private function sendHttp(array $c, string $to, string $text): void
    {
        $headers = [];
        foreach (preg_split('/\r?\n/', $this->secret($c['http_headers'])) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }

        // os valores são escapados para JSON antes de entrar no corpo
        $body = strtr($c['http_body'], [
            '{telefone}' => substr(json_encode($to), 1, -1),
            '{mensagem}' => substr(json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1),
        ]);

        $response = Http::timeout(10)->withHeaders($headers)->withBody($body, 'application/json')->post($c['http_url']);

        if ($response->failed()) {
            throw new TicketException('Provedor recusou a mensagem: HTTP '.$response->status());
        }
    }

    private function orderedVars(array $vars): array
    {
        return array_map(fn ($p) => $vars[trim($p, '{}')] ?? null, self::PLACEHOLDERS);
    }

    public static function encrypt(string $value): string
    {
        return $value === '' ? '' : Crypt::encryptString($value);
    }

    private function secret(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }
}
