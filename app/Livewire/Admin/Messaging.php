<?php

namespace App\Livewire\Admin;

use App\Exceptions\TicketException;
use App\Models\Setting;
use App\Models\TicketNotification;
use App\Services\MessagingService;
use App\Support\Phone;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mensagens (WhatsApp/SMS)')]
class Messaging extends Component
{
    public array $config = [];

    /** Segredos só são trocados quando o campo é preenchido. */
    public string $whatsappToken = '';

    public string $httpHeaders = '';

    public string $testPhone = '';

    public string $testType = 'issued';

    public function mount(): void
    {
        $this->config = Setting::get('messaging');
        unset($this->config['whatsapp_token'], $this->config['http_headers']);
    }

    public function save(): void
    {
        $this->validate([
            'config.provider' => ['required', Rule::in(array_keys(MessagingService::PROVIDERS))],
            'config.whatsapp_phone_number_id' => [$this->config['provider'] === 'whatsapp_cloud' ? 'required' : 'nullable', 'string', 'max:40'],
            'config.whatsapp_api_version' => ['nullable', 'string', 'max:10'],
            'config.whatsapp_language' => ['nullable', 'string', 'max:10'],
            'config.whatsapp_templates.*' => ['nullable', 'string', 'max:100'],
            'config.http_url' => [$this->config['provider'] === 'http' ? 'required' : 'nullable', 'url', 'max:500'],
            'config.http_body' => ['nullable', 'string', 'max:2000'],
            'config.texts.*' => ['required', 'string', 'max:600'],
            'whatsappToken' => ['nullable', 'string', 'max:1000'],
            'httpHeaders' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'config.whatsapp_phone_number_id' => 'Phone number ID', 'config.http_url' => 'URL',
        ]);

        if ($this->config['provider'] === 'whatsapp_cloud' && $this->whatsappToken === '' && Setting::get('messaging')['whatsapp_token'] === '') {
            $this->addError('whatsappToken', 'Informe o token de acesso.');

            return;
        }

        $stored = Setting::get('messaging');
        Setting::put('messaging', [
            ...$this->config,
            'whatsapp_token' => $this->whatsappToken !== '' ? MessagingService::encrypt($this->whatsappToken) : $stored['whatsapp_token'],
            'http_headers' => $this->httpHeaders !== '' ? MessagingService::encrypt($this->httpHeaders) : $stored['http_headers'],
        ]);

        $this->reset('whatsappToken', 'httpHeaders');
        $this->dispatch('toast', type: 'success', message: 'Configuração de mensagens salva.');
    }

    public function sendTest(MessagingService $messaging): void
    {
        $phone = Phone::normalize($this->testPhone);
        if (! $phone) {
            $this->addError('testPhone', 'Telefone inválido.');

            return;
        }

        try {
            $messaging->send($phone, $this->testType, [
                'senha' => 'A001', 'servico' => 'Atendimento Geral', 'unidade' => 'Unidade de teste',
                'local' => 'Guichê 01', 'posicao' => '2', 'link' => url('/'),
            ]);
            $this->dispatch('toast', type: 'success', message: 'Mensagem de teste enviada.');
        } catch (TicketException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        $stored = Setting::get('messaging');

        return view('livewire.admin.messaging', [
            'providers' => MessagingService::PROVIDERS,
            'types' => TicketNotification::TYPES,
            'hasToken' => $stored['whatsapp_token'] !== '',
            'hasHeaders' => $stored['http_headers'] !== '',
            'recent' => TicketNotification::with('ticket')->latest('id')->limit(15)->get(),
        ]);
    }
}
