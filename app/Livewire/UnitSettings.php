<?php

namespace App\Livewire;

use App\Enums\QueueType;
use App\Enums\ServiceType;
use App\Livewire\Concerns\InteractsWithUnit;
use App\Models\Department;
use App\Models\Location;
use App\Models\Service;
use App\Models\ServiceUser;
use App\Models\Unit;
use App\Models\UnitService;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Configurações da unidade')]
class UnitSettings extends Component
{
    use InteractsWithUnit;

    #[Url]
    public string $tab = 'services';

    // serviços
    public bool $showService = false;

    public ?int $unitServiceId = null;

    public array $serviceForm = [];

    public bool $showAdd = false;

    public array $toAdd = [];

    // impressão
    public array $print = [];

    // recursos opcionais (senha no celular, tempo estimado)
    public array $features = [];

    // atendentes
    #[Url(as: 'atendente')]
    public ?int $attendantId = null;

    public array $attendant = [];

    public ?int $addServiceId = null;

    public function mount(): void
    {
        $this->print = $this->unit()->only([
            'print_header', 'print_footer', 'print_show_date', 'print_show_priority',
            'print_show_unit_name', 'print_show_service_name', 'print_show_service_message',
        ]);

        $this->features = array_replace(Unit::DEFAULT_SETTINGS, $this->unit()->settings ?? []);

        if ($this->attendantId) {
            $this->selectAttendant($this->attendantId);
        }
    }

    // ------------------------------------------------------------ serviços

    #[Computed]
    public function unitServices(): Collection
    {
        return UnitService::with(['service', 'department'])->where('unit_id', $this->unit()->id)->get()->sortBy('service.name')->values();
    }

    #[Computed]
    public function availableServices(): Collection
    {
        return Service::main()->where('active', true)
            ->whereNotIn('id', $this->unitServices->pluck('service_id'))
            ->orderBy('name')->get();
    }

    public function toggleService(int $id): void
    {
        $us = $this->findUnitService($id);
        $us->update(['active' => ! $us->active]);
        unset($this->unitServices);
    }

    public function editService(int $id): void
    {
        $us = $this->findUnitService($id);
        $this->resetErrorBag();
        $this->unitServiceId = $us->id;
        $this->serviceForm = $us->only(['prefix', 'active', 'weight', 'increment', 'start_number', 'end_number', 'max_tickets', 'wait_target', 'message', 'department_id']);
        $this->serviceForm['type'] = $us->type->value;
        $this->showService = true;
    }

    public function saveService(): void
    {
        $data = $this->validate([
            'serviceForm.prefix' => ['required', 'string', 'max:3', 'regex:/^[A-Za-z]+$/'],
            'serviceForm.active' => ['boolean'],
            'serviceForm.weight' => ['required', 'integer', 'min:0', 'max:100'],
            'serviceForm.type' => ['required', Rule::enum(ServiceType::class)],
            'serviceForm.increment' => ['required', 'integer', 'min:1', 'max:100'],
            'serviceForm.start_number' => ['required', 'integer', 'min:0'],
            'serviceForm.end_number' => ['nullable', 'integer', 'gt:serviceForm.start_number'],
            'serviceForm.max_tickets' => ['nullable', 'integer', 'min:1'],
            'serviceForm.wait_target' => ['nullable', 'integer', 'min:1', 'max:600'],
            'serviceForm.message' => ['nullable', 'string', 'max:255'],
            'serviceForm.department_id' => ['nullable', 'exists:departments,id'],
        ], [], [
            'serviceForm.prefix' => 'sigla', 'serviceForm.start_number' => 'número inicial',
            'serviceForm.end_number' => 'número final', 'serviceForm.max_tickets' => 'máximo de senhas',
        ])['serviceForm'];

        $data['prefix'] = strtoupper($data['prefix']);
        $data['message'] ??= '';
        foreach (['end_number', 'max_tickets', 'wait_target', 'department_id'] as $nullable) {
            $data[$nullable] = $data[$nullable] ?: null;
        }

        $this->findUnitService($this->unitServiceId)->update($data);
        $this->showService = false;
        unset($this->unitServices);
        $this->toast('Serviço atualizado.');
    }

    public function addServices(): void
    {
        $count = $this->unitServices->count();
        foreach ($this->availableServices->whereIn('id', array_map('intval', $this->toAdd)) as $service) {
            UnitService::create([
                'unit_id' => $this->unit()->id,
                'service_id' => $service->id,
                'prefix' => Service::prefixFor(++$count),
                'active' => false,
            ]);
        }

        $this->reset('toAdd');
        $this->showAdd = false;
        unset($this->unitServices, $this->availableServices);
        $this->toast('Serviços adicionados. Revise as siglas e ative-os.');
    }

    public function removeService(int $id): void
    {
        $us = $this->findUnitService($id);
        if ($us->active) {
            $this->toast('Desative o serviço antes de removê-lo.', 'error');

            return;
        }

        ServiceUser::where('unit_id', $us->unit_id)->where('service_id', $us->service_id)->delete();
        $us->delete();
        unset($this->unitServices);
        $this->toast('Serviço removido da unidade.');
    }

    public function resetCounter(int $id): void
    {
        $this->findUnitService($id)->resetCounter();
        unset($this->unitServices);
        $this->toast('Contador reiniciado.');
    }

    private function findUnitService(int $id): UnitService
    {
        return UnitService::where('unit_id', $this->unit()->id)->findOrFail($id);
    }

    // ------------------------------------------------------------ impressão e rotinas

    public function savePrint(): void
    {
        $data = $this->validate([
            'print.print_header' => ['nullable', 'string', 'max:150'],
            'print.print_footer' => ['nullable', 'string', 'max:150'],
            'print.print_show_date' => ['boolean'],
            'print.print_show_priority' => ['boolean'],
            'print.print_show_unit_name' => ['boolean'],
            'print.print_show_service_name' => ['boolean'],
            'print.print_show_service_message' => ['boolean'],
        ])['print'];

        $this->unit()->update(array_map(fn ($v) => $v ?? '', $data));
        $this->toast('Configuração de impressão salva.');
    }

    public function archive(TicketService $tickets): void
    {
        $count = $tickets->archive($this->unit());
        unset($this->unitServices);
        $this->toast("{$count} senha(s) arquivada(s). Contadores reiniciados.");
    }

    public function clear(TicketService $tickets): void
    {
        $tickets->clear($this->unit());
        unset($this->unitServices);
        $this->toast('Atendimentos da unidade apagados.');
    }

    public function saveFeatures(): void
    {
        $data = $this->validate([
            'features.mobile_ticket' => ['boolean'],
            'features.mobile_show_position' => ['boolean'],
            'features.mobile_show_eta' => ['boolean'],
            'features.mobile_near_alert' => ['boolean'],
            'features.mobile_near_threshold' => ['required', 'integer', 'min:1', 'max:20'],
            'features.triage_show_qr' => ['boolean'],
            'features.triage_show_eta' => ['boolean'],
            'features.eta_window' => ['required', 'integer', 'min:15', 'max:480'],
            'features.sla_enabled' => ['boolean'],
            'features.sla_default_target' => ['required', 'integer', 'min:1', 'max:600'],
            'features.sla_warning_percent' => ['required', 'integer', 'min:10', 'max:100'],
            'features.sla_monitor_sound' => ['boolean'],
            'features.sla_attendance_highlight' => ['boolean'],
            'features.pauses_enabled' => ['boolean'],
            'features.pause_require_reason' => ['boolean'],
            'features.pause_alert_exceeded' => ['boolean'],
            'features.direct_print_enabled' => ['boolean'],
            'features.kiosk_enabled' => ['boolean'],
            'features.notify_enabled' => ['boolean'],
            'features.notify_on_issue' => ['boolean'],
            'features.notify_near' => ['boolean'],
            'features.notify_near_threshold' => ['required', 'integer', 'min:1', 'max:20'],
            'features.notify_on_call' => ['boolean'],
            'features.survey_enabled' => ['boolean'],
            'features.survey_scale' => ['required', 'in:nps,csat'],
            'features.survey_question' => ['required', 'string', 'max:200'],
            'features.survey_comment' => ['boolean'],
            'features.survey_via_message' => ['boolean'],
            'features.survey_on_tracking' => ['boolean'],
        ], [], [
            'features.survey_question' => 'pergunta',
            'features.sla_default_target' => 'meta padrão',
            'features.sla_warning_percent' => 'percentual de alerta',
            'features.mobile_near_threshold' => 'quantidade de senhas',
            'features.eta_window' => 'janela de cálculo',
        ])['features'];

        $unit = $this->unit();
        $unit->update(['settings' => array_replace($unit->settings ?? [], $data)]);
        $this->dispatch('features-saved');
        $this->toast('Configuração salva.');
    }

    // ------------------------------------------------------------ atendentes

    #[Computed]
    public function attendants(): Collection
    {
        return User::where('active', true)
            ->whereHas('allocations', fn ($q) => $q->where('unit_id', $this->unit()->id))
            ->orderBy('name')->get();
    }

    #[Computed]
    public function attendantServices(): Collection
    {
        return $this->attendantId
            ? ServiceUser::with('service')->where('unit_id', $this->unit()->id)->where('user_id', $this->attendantId)->get()->sortBy('service.name')
            : collect();
    }

    public function updatedAttendantId($value): void
    {
        $value ? $this->selectAttendant((int) $value) : $this->reset('attendant');
    }

    private function selectAttendant(int $id): void
    {
        $user = $this->attendants->firstWhere('id', $id);
        if (! $user) {
            $this->attendantId = null;

            return;
        }

        $behavior = $user->behavior ?? [];
        $this->attendant = [
            'location_id' => $user->location_id,
            'location_number' => $user->location_number,
            'queue_type' => $user->queue_type?->value ?? 'all',
            'call_by_service' => $this->triState($behavior['call_by_service'] ?? null),
            'call_out_of_order' => $this->triState($behavior['call_out_of_order'] ?? null),
            'change_queue_type' => $this->triState($behavior['change_queue_type'] ?? null),
        ];
    }

    public function saveAttendant(): void
    {
        $this->validate([
            'attendant.location_id' => ['nullable', 'exists:locations,id'],
            'attendant.location_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'attendant.queue_type' => ['required', Rule::enum(QueueType::class)],
            'attendant.call_by_service' => ['in:,1,0'],
            'attendant.call_out_of_order' => ['in:,1,0'],
            'attendant.change_queue_type' => ['in:,1,0'],
        ]);

        $user = $this->attendants->firstWhere('id', $this->attendantId) ?? abort(404);
        $fromTri = fn ($v) => $v === '' || $v === null ? null : (bool) $v;

        $user->update([
            'location_id' => $this->attendant['location_id'] ?: null,
            'location_number' => $this->attendant['location_number'] ?: null,
            'queue_type' => $this->attendant['queue_type'],
            'behavior' => array_filter([
                'call_by_service' => $fromTri($this->attendant['call_by_service']),
                'call_out_of_order' => $fromTri($this->attendant['call_out_of_order']),
                'change_queue_type' => $fromTri($this->attendant['change_queue_type']),
            ], fn ($v) => $v !== null) ?: null,
        ]);

        $this->toast('Atendente atualizado.');
    }

    public function addAttendantService(): void
    {
        $valid = $this->unitServices->pluck('service_id');
        if (! $this->attendantId || ! $valid->contains($this->addServiceId)) {
            return;
        }

        ServiceUser::firstOrCreate(['unit_id' => $this->unit()->id, 'user_id' => $this->attendantId, 'service_id' => $this->addServiceId]);
        $this->addServiceId = null;
        unset($this->attendantServices);
    }

    public function addAllAttendantServices(): void
    {
        foreach ($this->unitServices->where('active', true) as $us) {
            ServiceUser::firstOrCreate(['unit_id' => $this->unit()->id, 'user_id' => $this->attendantId, 'service_id' => $us->service_id]);
        }
        unset($this->attendantServices);
    }

    public function updateAttendantServiceWeight(int $id, $weight): void
    {
        $weight = max(1, min(100, (int) $weight));
        ServiceUser::where('unit_id', $this->unit()->id)->whereKey($id)->update(['weight' => $weight]);
        unset($this->attendantServices);
    }

    public function removeAttendantService(int $id): void
    {
        ServiceUser::where('unit_id', $this->unit()->id)->whereKey($id)->delete();
        unset($this->attendantServices);
    }

    private function triState(?bool $v): string
    {
        return $v === null ? '' : ($v ? '1' : '0');
    }

    public function getListeners(): array
    {
        return [];
    }

    public function render()
    {
        return view('livewire.unit-settings', [
            'departments' => Department::where('active', true)->orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
            'queueTypes' => QueueType::cases(),
            'serviceTypes' => ServiceType::cases(),
        ]);
    }
}
