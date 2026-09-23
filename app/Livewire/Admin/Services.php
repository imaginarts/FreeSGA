<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\CrudModal;
use App\Models\Service;
use App\Models\ServiceUser;
use App\Models\UnitService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Serviços')]
class Services extends Component
{
    use CrudModal;

    protected function model(): string
    {
        return Service::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'description' => '', 'weight' => 1, 'parent_id' => null, 'active' => true];
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:50'],
            'form.description' => ['nullable', 'string', 'max:250'],
            'form.weight' => ['required', 'integer', 'min:0', 'max:100'],
            'form.parent_id' => ['nullable', Rule::exists('services', 'id')->whereNull('parent_id'), Rule::notIn([$this->editingId])],
            'form.active' => ['boolean'],
        ];
    }

    protected function attributes(): array
    {
        return ['form.name' => 'nome', 'form.weight' => 'peso', 'form.parent_id' => 'serviço principal'];
    }

    protected function payload(array $data): array
    {
        $data['description'] ??= '';
        $data['parent_id'] = $data['parent_id'] ?: null;

        // serviço que já tem subserviços não pode virar subserviço
        if ($this->editingId && $data['parent_id'] && Service::where('parent_id', $this->editingId)->exists()) {
            $data['parent_id'] = null;
        }

        return $data;
    }

    public function createChild(int $parentId): void
    {
        $this->create();
        $this->form['parent_id'] = $parentId;
    }

    protected function cannotDelete(Model $record): ?string
    {
        if (UnitService::where('service_id', $record->id)->where('active', true)->exists()) {
            return 'O serviço está ativo em alguma unidade.';
        }
        if ($record->children()->exists()) {
            return 'Remova os subserviços antes.';
        }

        UnitService::where('service_id', $record->id)->delete();
        ServiceUser::where('service_id', $record->id)->delete();

        return null;
    }

    public function render()
    {
        return view('livewire.admin.services', [
            'services' => Service::main()
                ->with(['children'])
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')->paginate(20),
            'parents' => Service::main()->where('id', '!=', $this->editingId)->orderBy('name')->get(),
        ]);
    }
}
