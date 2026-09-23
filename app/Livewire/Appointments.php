<?php

namespace App\Livewire;

use App\Enums\AppointmentStatus;
use App\Livewire\Concerns\InteractsWithUnit;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\UnitService;
use App\Support\Privacy;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Agendamentos')]
class Appointments extends Component
{
    use InteractsWithUnit, WithPagination;

    #[Url]
    public string $date = '';

    #[Url]
    public string $status = '';

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    // cliente: busca ou cadastro rápido
    public string $customerSearch = '';

    public bool $newCustomer = false;

    public function mount(): void
    {
        $this->date = $this->date ?: CarbonImmutable::now($this->unit()->timezone())->toDateString();
    }

    public function updated($property): void
    {
        if (in_array($property, ['date', 'status', 'search'])) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->resetErrorBag();
        $this->reset('editingId', 'customerSearch', 'newCustomer');
        $this->form = ['date' => $this->date, 'time' => '09:00', 'service_id' => null, 'customer_id' => null, 'customer_name' => '', 'customer_document' => '', 'customer_phone' => ''];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $a = $this->find($id);
        if ($a->status !== AppointmentStatus::Scheduled) {
            $this->toast('Agendamentos confirmados ou expirados não podem ser alterados.', 'error');

            return;
        }

        $this->resetErrorBag();
        $this->reset('customerSearch', 'newCustomer');
        $this->editingId = $a->id;
        $this->form = ['date' => $a->date->format('Y-m-d'), 'time' => substr($a->time, 0, 5), 'service_id' => $a->service_id, 'customer_id' => $a->customer_id, 'customer_name' => '', 'customer_document' => '', 'customer_phone' => ''];
        $this->customerSearch = Privacy::document($a->customer->document).' - '.$a->customer->name;
        $this->showForm = true;
    }

    public function selectCustomer(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->form['customer_id'] = $customer->id;
        $this->customerSearch = Privacy::document($customer->document).' - '.$customer->name;
    }

    public function save(): void
    {
        $unitServices = UnitService::where('unit_id', $this->unit()->id)->where('active', true)->pluck('service_id');

        $this->validate([
            'form.date' => ['required', 'date'],
            'form.time' => ['required', 'date_format:H:i'],
            'form.service_id' => ['required', Rule::in($unitServices)],
            'form.customer_id' => [$this->newCustomer ? 'nullable' : 'required', 'nullable', 'exists:customers,id'],
            'form.customer_name' => [$this->newCustomer ? 'required' : 'nullable', 'string', 'min:3', 'max:60'],
            'form.customer_document' => [$this->newCustomer ? 'required' : 'nullable', 'string', 'max:30', Rule::unique('customers', 'document')],
            'form.customer_phone' => ['nullable', 'string', 'max:25'],
        ], [], [
            'form.date' => 'data', 'form.time' => 'hora', 'form.service_id' => 'serviço', 'form.customer_id' => 'cliente',
            'form.customer_name' => 'nome', 'form.customer_document' => 'documento',
        ]);

        $customerId = $this->newCustomer
            ? Customer::create(['name' => $this->form['customer_name'], 'document' => $this->form['customer_document'], 'phone' => $this->form['customer_phone'] ?: null])->id
            : $this->form['customer_id'];

        $data = [
            'unit_id' => $this->unit()->id,
            'service_id' => $this->form['service_id'],
            'customer_id' => $customerId,
            'date' => $this->form['date'],
            'time' => $this->form['time'].':00',
        ];

        $this->editingId ? $this->find($this->editingId)->update($data) : Appointment::create($data);

        $this->showForm = false;
        $this->toast('Agendamento salvo.');
    }

    public function delete(int $id): void
    {
        $a = $this->find($id);
        if ($a->status === AppointmentStatus::Confirmed) {
            $this->toast('Agendamento já confirmado não pode ser removido.', 'error');

            return;
        }
        $a->delete();
        $this->toast('Agendamento removido.');
    }

    private function find(int $id): Appointment
    {
        return Appointment::with('customer')->where('unit_id', $this->unit()->id)->findOrFail($id);
    }

    public function getListeners(): array
    {
        return [];
    }

    public function render()
    {
        $term = trim($this->customerSearch);

        return view('livewire.appointments', [
            'appointments' => Appointment::with(['customer', 'service', 'ticket'])
                ->where('unit_id', $this->unit()->id)
                ->when($this->date, fn ($q) => $q->whereDate('date', $this->date))
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->when($this->search, fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")->orWhere('document', 'like', "{$this->search}%")))
                ->orderBy('date')->orderBy('time')
                ->paginate(30),
            'services' => UnitService::with('service')->where('unit_id', $this->unit()->id)->where('active', true)->get()->sortBy('service.name'),
            'statuses' => AppointmentStatus::cases(),
            'customerOptions' => $this->showForm && ! $this->form['customer_id'] && strlen($term) >= 2
                ? Customer::where('name', 'like', "%$term%")->orWhere('document', 'like', "$term%")->orderBy('name')->limit(8)->get()
                : collect(),
        ]);
    }

    public function updatedCustomerSearch(): void
    {
        $this->form['customer_id'] = null;
    }
}
