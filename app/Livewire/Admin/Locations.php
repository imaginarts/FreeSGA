<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\CrudModal;
use App\Models\Location;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Locais')]
class Locations extends Component
{
    use CrudModal;

    protected function model(): string
    {
        return Location::class;
    }

    protected function defaults(): array
    {
        return ['name' => ''];
    }

    protected function rules(): array
    {
        return ['form.name' => ['required', 'string', 'max:20', Rule::unique('locations', 'name')->ignore($this->editingId)]];
    }

    protected function attributes(): array
    {
        return ['form.name' => 'nome'];
    }

    protected function cannotDelete(Model $record): ?string
    {
        return Ticket::where('location_id', $record->id)->exists() ? 'Este local já foi usado em atendimentos.' : null;
    }

    public function render()
    {
        return view('livewire.admin.simple-list', [
            'title' => 'Locais',
            'subtitle' => 'Tipos de ponto de atendimento exibidos no painel (Guichê, Mesa, Sala…)',
            'singular' => 'local',
            'records' => Location::orderBy('name')->paginate(20),
            'columns' => ['name' => 'Nome'],
            'fields' => [['name', 'Nome', 'text']],
        ]);
    }
}
