<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\CrudModal;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Unidades')]
class Units extends Component
{
    use CrudModal;

    protected function model(): string
    {
        return Unit::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'description' => '', 'timezone' => 'America/Sao_Paulo', 'active' => true];
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:50'],
            'form.description' => ['nullable', 'string', 'max:250'],
            'form.timezone' => ['nullable', 'timezone'],
            'form.active' => ['boolean'],
        ];
    }

    protected function attributes(): array
    {
        return ['form.name' => 'nome', 'form.description' => 'descrição', 'form.timezone' => 'fuso horário'];
    }

    protected function payload(array $data): array
    {
        $data['description'] ??= '';

        return $data;
    }

    protected function cannotDelete(Model $record): ?string
    {
        return $record->unitServices()->where('active', true)->exists()
            ? 'Desative os serviços da unidade antes de removê-la.'
            : null;
    }

    public function render()
    {
        return view('livewire.admin.units', [
            'units' => Unit::withCount(['allocations', 'unitServices' => fn ($q) => $q->where('active', true)])
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')->paginate(20),
            'timezones' => collect(\DateTimeZone::listIdentifiers(\DateTimeZone::AMERICA))->filter(fn ($tz) => str_contains($tz, 'America/')),
        ]);
    }
}
