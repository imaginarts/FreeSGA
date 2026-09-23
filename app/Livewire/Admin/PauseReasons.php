<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\CrudModal;
use App\Models\PauseReason;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Motivos de pausa')]
class PauseReasons extends Component
{
    use CrudModal;

    protected function model(): string
    {
        return PauseReason::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'max_minutes' => null, 'active' => true];
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:50'],
            'form.max_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'form.active' => ['boolean'],
        ];
    }

    protected function attributes(): array
    {
        return ['form.name' => 'nome', 'form.max_minutes' => 'tempo máximo'];
    }

    protected function payload(array $data): array
    {
        $data['max_minutes'] = $data['max_minutes'] ?: null;

        return $data;
    }

    public function render()
    {
        return view('livewire.admin.simple-list', [
            'title' => 'Motivos de pausa',
            'subtitle' => 'Usados pelo atendente ao pausar; o tempo máximo gera alerta no monitor',
            'singular' => 'motivo',
            'records' => PauseReason::orderBy('name')->paginate(20),
            'columns' => ['name' => 'Nome', 'max_minutes' => 'Tempo máximo (min)', 'active' => 'Status'],
            'fields' => [['name', 'Nome', 'text'], ['max_minutes', 'Tempo máximo (minutos, opcional)', 'number'], ['active', 'Ativo', 'checkbox']],
        ]);
    }
}
