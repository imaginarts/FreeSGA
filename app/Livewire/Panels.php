<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithUnit;
use App\Models\Panel;
use App\Models\UnitService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Painéis')]
class Panels extends Component
{
    use InteractsWithUnit;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public array $services = [];

    public array $settings = [];

    public function create(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->name = '';
        $this->services = UnitService::where('unit_id', $this->unit()->id)->where('active', true)->pluck('service_id')->map('strval')->all();
        $this->settings = Panel::DEFAULT_SETTINGS;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $panel = $this->findPanel($id);
        $this->resetErrorBag();
        $this->editingId = $panel->id;
        $this->name = $panel->name;
        $this->services = $panel->services()->pluck('services.id')->map('strval')->all();
        $this->settings = array_replace(Panel::DEFAULT_SETTINGS, $panel->settings ?? []);
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'services' => ['required', 'array', 'min:1'],
            'settings.highlight_bg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'settings.history_bg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'settings.footer_bg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'settings.voice' => ['boolean'],
            'settings.sound' => ['boolean'],
            'settings.footer_text' => ['nullable', 'string', 'max:150'],
            'settings.show_eta' => ['boolean'],
        ], [], ['name' => 'nome', 'services' => 'serviços']);

        $panel = $this->editingId
            ? tap($this->findPanel($this->editingId))->update(['name' => $this->name, 'settings' => $this->settings])
            : Panel::create(['unit_id' => $this->unit()->id, 'name' => $this->name, 'settings' => $this->settings]);

        $allowed = UnitService::where('unit_id', $this->unit()->id)->pluck('service_id');
        $panel->services()->sync($allowed->intersect(array_map('intval', $this->services))->values());

        $this->showForm = false;
        $this->toast('Painel salvo.');
    }

    public function delete(int $id): void
    {
        $this->findPanel($id)->delete();
        $this->toast('Painel removido.');
    }

    private function findPanel(int $id): Panel
    {
        return Panel::where('unit_id', $this->unit()->id)->findOrFail($id);
    }

    public function getListeners(): array
    {
        return [];
    }

    public function render()
    {
        return view('livewire.panels', [
            'panels' => Panel::withCount('services')->where('unit_id', $this->unit()->id)->orderBy('name')->get(),
            'unitServices' => UnitService::with('service')->where('unit_id', $this->unit()->id)->get()->sortBy('service.name'),
        ]);
    }
}
