<?php

namespace App\Livewire\Admin;

use App\Enums\WebhookEvent;
use App\Livewire\Concerns\CrudModal;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Webhooks')]
class Webhooks extends Component
{
    use CrudModal;

    protected function model(): string
    {
        return Webhook::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'url' => '', 'headers' => [], 'events' => [], 'enabled' => true];
    }

    protected function toForm(Model $record): array
    {
        return [
            'name' => $record->name,
            'url' => $record->url,
            'enabled' => $record->enabled,
            'events' => $record->events ?? [],
            'headers' => collect($record->headers ?? [])->map(fn ($v, $k) => ['key' => $k, 'value' => $v])->values()->all(),
        ];
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:80'],
            'form.url' => ['required', 'url', 'max:255'],
            'form.enabled' => ['boolean'],
            'form.events' => ['required', 'array', 'min:1'],
            'form.events.*' => [Rule::enum(WebhookEvent::class)],
            'form.headers' => ['array'],
            'form.headers.*.key' => ['nullable', 'string', 'max:100'],
            'form.headers.*.value' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function attributes(): array
    {
        return ['form.name' => 'nome', 'form.events' => 'eventos'];
    }

    protected function payload(array $data): array
    {
        $data['headers'] = collect($data['headers'] ?? [])
            ->filter(fn ($h) => trim($h['key'] ?? '') !== '')
            ->mapWithKeys(fn ($h) => [trim($h['key']) => $h['value'] ?? ''])
            ->all();
        $data['events'] = array_values($data['events']);

        return $data;
    }

    public function addHeader(): void
    {
        $this->form['headers'][] = ['key' => '', 'value' => ''];
    }

    public function removeHeader(int $index): void
    {
        unset($this->form['headers'][$index]);
        $this->form['headers'] = array_values($this->form['headers']);
    }

    public function render()
    {
        return view('livewire.admin.webhooks', [
            'webhooks' => Webhook::orderBy('name')->paginate(20),
            'events' => WebhookEvent::cases(),
        ]);
    }
}
