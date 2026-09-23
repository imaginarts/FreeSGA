<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\CrudModal;
use App\Models\Department;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Departamentos')]
class Departments extends Component
{
    use CrudModal;

    protected function model(): string
    {
        return Department::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'description' => '', 'active' => true];
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:64'],
            'form.description' => ['nullable', 'string', 'max:250'],
            'form.active' => ['boolean'],
        ];
    }

    protected function attributes(): array
    {
        return ['form.name' => 'nome'];
    }

    protected function payload(array $data): array
    {
        $data['description'] ??= '';

        return $data;
    }

    public function render()
    {
        return view('livewire.admin.simple-list', [
            'title' => 'Departamentos',
            'subtitle' => 'Agrupamento opcional dos serviços na unidade',
            'singular' => 'departamento',
            'records' => Department::orderBy('name')->paginate(20),
            'columns' => ['name' => 'Nome', 'description' => 'Descrição', 'active' => 'Status'],
            'fields' => [['name', 'Nome', 'text'], ['description', 'Descrição', 'text'], ['active', 'Ativo', 'checkbox']],
        ]);
    }
}
