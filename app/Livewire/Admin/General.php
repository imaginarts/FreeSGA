<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Services\QueueService;
use App\Services\TicketService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Administração')]
class General extends Component
{
    public array $appearance = [];

    public array $behavior = [];

    public array $ordering = [];

    public function mount(): void
    {
        $this->appearance = Setting::get('appearance');
        $this->behavior = Setting::get('behavior');
        $this->ordering = array_pad(Setting::get('queue')['ordering'], 5, ['field' => '', 'order' => 'asc']);
    }

    public function saveAppearance(): void
    {
        $this->validate([
            'appearance.app_name' => ['required', 'string', 'max:40'],
            'appearance.primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [], ['appearance.app_name' => 'nome do sistema', 'appearance.primary_color' => 'cor']);

        Setting::put('appearance', $this->appearance);
        $this->dispatch('toast', type: 'success', message: 'Aparência salva. Recarregue a página para ver as mudanças.');
    }

    public function saveBehavior(): void
    {
        $this->validate([
            'behavior.priority_swap' => ['boolean'],
            'behavior.priority_swap_method' => ['required', 'in:unit,user'],
            'behavior.priority_swap_count' => ['required', 'integer', 'min:1', 'max:10'],
            'behavior.call_by_service' => ['boolean'],
            'behavior.call_out_of_order' => ['boolean'],
            'behavior.change_queue_type' => ['boolean'],
            'behavior.appointment_delay' => ['required', 'integer', 'min:0', 'max:1440'],
        ]);

        Setting::put('behavior', $this->behavior);
        $this->dispatch('toast', type: 'success', message: 'Comportamento salvo.');
    }

    public function saveOrdering(): void
    {
        $this->validate([
            'ordering.*.field' => ['nullable', Rule::in(array_keys(QueueService::ORDERING_FIELDS))],
            'ordering.*.order' => ['required', 'in:asc,desc'],
        ]);

        Setting::put('queue', ['ordering' => array_values(array_filter($this->ordering, fn ($r) => ! empty($r['field'])))]);
        $this->dispatch('toast', type: 'success', message: 'Ordenação da fila salva.');
    }

    public function archiveAll(TicketService $tickets): void
    {
        $count = $tickets->archive();
        $this->dispatch('toast', type: 'success', message: "{$count} senha(s) arquivada(s) e contadores reiniciados.");
    }

    public function clearAll(TicketService $tickets): void
    {
        $tickets->clear();
        $this->dispatch('toast', type: 'success', message: 'Todos os atendimentos foram apagados.');
    }

    public function render()
    {
        return view('livewire.admin.general', ['fields' => QueueService::ORDERING_FIELDS]);
    }
}
