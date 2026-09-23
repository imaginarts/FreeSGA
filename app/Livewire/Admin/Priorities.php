<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\CrudModal;
use App\Models\Priority;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Prioridades')]
class Priorities extends Component
{
    use CrudModal;

    protected function model(): string
    {
        return Priority::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'description' => '', 'weight' => 1, 'color' => '#dc2626', 'active' => true];
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:64'],
            'form.description' => ['nullable', 'string', 'max:100'],
            'form.weight' => ['required', 'integer', 'min:0', 'max:100'],
            'form.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'form.active' => ['boolean'],
        ];
    }

    protected function attributes(): array
    {
        return ['form.name' => 'nome', 'form.weight' => 'peso', 'form.color' => 'cor'];
    }

    protected function payload(array $data): array
    {
        $data['description'] ??= '';

        return $data;
    }

    protected function cannotDelete(Model $record): ?string
    {
        if ($record->weight === 0 && Priority::where('weight', 0)->count() === 1) {
            return 'É necessário manter ao menos uma prioridade normal (peso 0).';
        }

        return null;
    }

    public function render()
    {
        return view('livewire.admin.priorities', [
            'priorities' => Priority::orderBy('weight')->orderBy('name')->paginate(20),
        ]);
    }
}
