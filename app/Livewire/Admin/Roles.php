<?php

namespace App\Livewire\Admin;

use App\Enums\Module;
use App\Livewire\Concerns\CrudModal;
use App\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Perfis')]
class Roles extends Component
{
    use CrudModal;

    protected function model(): string
    {
        return Role::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'description' => '', 'modules' => []];
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:50'],
            'form.description' => ['nullable', 'string', 'max:150'],
            'form.modules' => ['array'],
            'form.modules.*' => [Rule::enum(Module::class)],
        ];
    }

    protected function attributes(): array
    {
        return ['form.name' => 'nome'];
    }

    protected function payload(array $data): array
    {
        $data['description'] ??= '';
        $data['modules'] = array_values($data['modules'] ?? []);

        return $data;
    }

    protected function cannotDelete(Model $record): ?string
    {
        return $record->allocations()->exists() ? 'Há usuários lotados com este perfil.' : null;
    }

    public function render()
    {
        return view('livewire.admin.roles', [
            'roles' => Role::withCount('allocations')->orderBy('name')->paginate(20),
            'modules' => Module::cases(),
        ]);
    }
}
