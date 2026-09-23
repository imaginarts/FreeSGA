<?php

namespace App\Livewire;

use App\Livewire\Concerns\CrudModal;
use App\Models\Customer;
use App\Models\Ticket;
use App\Services\PrivacyService;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Clientes')]
class Customers extends Component
{
    use CrudModal;

    public ?int $historyId = null;

    public bool $showHistory = false;

    protected function model(): string
    {
        return Customer::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => '', 'document' => '', 'email' => null, 'phone' => null, 'birth_date' => null, 'gender' => null, 'notes' => null,
            'address_zip' => null, 'address_street' => null, 'address_number' => null, 'address_complement' => null,
            'address_city' => null, 'address_state' => null, 'address_country' => 'BR',
        ];
    }

    protected function toForm(Model $record): array
    {
        return [...array_intersect_key($record->toArray(), $this->defaults()), 'birth_date' => $record->birth_date?->format('Y-m-d')];
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'min:3', 'max:60'],
            'form.document' => ['required', 'string', 'min:3', 'max:30', Rule::unique('customers', 'document')->ignore($this->editingId)],
            'form.email' => ['nullable', 'email', 'max:80'],
            'form.phone' => ['nullable', 'string', 'max:25'],
            'form.birth_date' => ['nullable', 'date', 'before:today'],
            'form.gender' => ['nullable', 'in:M,F,O'],
            'form.notes' => ['nullable', 'string', 'max:2000'],
            'form.address_zip' => ['nullable', 'string', 'max:25'],
            'form.address_street' => ['nullable', 'string', 'max:60'],
            'form.address_number' => ['nullable', 'string', 'max:10'],
            'form.address_complement' => ['nullable', 'string', 'max:15'],
            'form.address_city' => ['nullable', 'string', 'max:30'],
            'form.address_state' => ['nullable', 'string', 'max:3'],
            'form.address_country' => ['nullable', 'string', 'size:2'],
        ];
    }

    protected function attributes(): array
    {
        return ['form.name' => 'nome', 'form.document' => 'documento', 'form.birth_date' => 'nascimento'];
    }

    protected function payload(array $data): array
    {
        return array_map(fn ($v) => $v === '' ? null : $v, $data);
    }

    protected function cannotDelete(Model $record): ?string
    {
        return $record->tickets()->exists() || $record->appointments()->exists()
            ? 'O cliente possui senhas ou agendamentos registrados.'
            : null;
    }

    public function history(int $id): void
    {
        $this->historyId = $id;
        $this->showHistory = true;
        Audit::log('privacy.viewed', "Histórico de senhas do cliente #{$id} consultado", Customer::find($id));
    }

    /** Portabilidade: entrega ao titular todos os dados pessoais em JSON. */
    public function exportData(int $id)
    {
        $customer = Customer::findOrFail($id);
        $data = app(PrivacyService::class)->export($customer);

        return response()->streamDownload(
            fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            "dados-cliente-{$customer->id}.json",
            ['Content-Type' => 'application/json'],
        );
    }

    /** Eliminação: remove os dados pessoais mantendo as estatísticas. */
    public function anonymize(int $id): void
    {
        app(PrivacyService::class)->anonymize(Customer::findOrFail($id));
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Dados pessoais do cliente anonimizados.');
    }

    public function render()
    {
        $term = trim($this->search);

        return view('livewire.customers', [
            'customers' => Customer::withCount('tickets')
                ->when($term, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%$term%")->orWhere('document', 'like', "$term%")->orWhere('email', 'like', "%$term%")))
                ->orderBy('name')->paginate(20),
            'historyCustomer' => $this->historyId ? Customer::find($this->historyId) : null,
            'historyTickets' => $this->historyId
                ? Ticket::with(['unit', 'service', 'priority'])->where('customer_id', $this->historyId)->latest('id')->limit(100)->get()
                : collect(),
        ]);
    }
}
