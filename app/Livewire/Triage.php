<?php

namespace App\Livewire;

use App\Enums\AppointmentStatus;
use App\Enums\TicketStatus;
use App\Livewire\Concerns\InteractsWithUnit;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Printer;
use App\Models\Priority;
use App\Models\Ticket;
use App\Models\UnitService;
use App\Services\TicketPrinter;
use App\Services\TicketService;
use App\Services\WaitEstimator;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Triagem')]
class Triage extends Component
{
    use InteractsWithUnit;

    public ?int $lastTicketId = null;

    // modal de prioridade / cliente
    public bool $showIssue = false;

    public ?int $issueServiceId = null;

    public ?int $issuePriorityId = null;

    public bool $issuePriority = false;

    public string $customerDocument = '';

    public string $customerName = '';

    public string $customerPhone = '';

    // busca
    public string $search = '';

    public bool $showSearch = false;

    // agendamentos
    public bool $showAppointments = false;

    #[Computed]
    public function unitServices(): Collection
    {
        return UnitService::with('service.children')
            ->where('unit_id', $this->unit()->id)
            ->active()
            ->get()
            ->sortBy('service.name')
            ->values();
    }

    #[Computed]
    public function priorities(): Collection
    {
        return Priority::active()->orderBy('weight')->orderBy('name')->get();
    }

    #[Computed]
    public function counts(): Collection
    {
        return Ticket::current()
            ->where('unit_id', $this->unit()->id)
            ->selectRaw('service_id, count(*) as total, sum(case when status = ? then 1 else 0 end) as waiting', [TicketStatus::Issued->value])
            ->groupBy('service_id')
            ->get()
            ->keyBy('service_id');
    }

    /** Tempo estimado para quem pegar senha agora, por serviço. */
    #[Computed]
    public function estimates(): array
    {
        $unit = $this->unit();

        return $unit->feature('triage_show_eta')
            ? app(WaitEstimator::class)->forServices($unit, $this->unitServices->pluck('service_id')->all())
            : [];
    }

    #[Computed]
    public function lastTicket(): ?Ticket
    {
        return $this->lastTicketId ? Ticket::with(['service', 'priority'])->find($this->lastTicketId) : null;
    }

    #[Computed]
    public function searchResults(): Collection
    {
        if (trim($this->search) === '') {
            return collect();
        }

        preg_match('/^\s*([a-zA-Z]*)\s*(\d*)\s*$/', $this->search, $m);

        return Ticket::with(['service', 'priority'])
            ->current()
            ->where('unit_id', $this->unit()->id)
            ->when($m[1] ?? '', fn ($q, $prefix) => $q->where('prefix', strtoupper($prefix)))
            ->when((int) ($m[2] ?? 0), fn ($q, $number) => $q->where('number', $number))
            ->latest('id')
            ->limit(30)
            ->get();
    }

    #[Computed]
    public function appointments(): Collection
    {
        $unit = $this->unit();

        return Appointment::with(['customer', 'service'])
            ->where('unit_id', $unit->id)
            ->whereDate('date', CarbonImmutable::now($unit->timezone())->toDateString())
            ->orderBy('time')
            ->get();
    }

    /** Clique no botão Normal/Prioridade de um serviço. */
    public function choose(int $serviceId, bool $priority): void
    {
        $this->resetErrorBag();
        $this->issueServiceId = $serviceId;
        $this->issuePriority = $priority;

        $options = $this->priorities->filter(fn (Priority $p) => $priority ? $p->weight > 0 : $p->weight === 0);
        $this->issuePriorityId = $options->first()?->id;

        // sem cliente e com uma única opção: emite direto
        if ($options->count() === 1 && ! $this->askCustomer()) {
            $this->issue();

            return;
        }

        $this->showIssue = true;
    }

    public function issue(): void
    {
        $priority = Priority::find($this->issuePriorityId);
        if (! $priority) {
            $this->toast('Selecione a prioridade.', 'error');

            return;
        }

        $customer = trim($this->customerDocument) !== '' ? ['document' => $this->customerDocument, 'name' => $this->customerName, 'phone' => $this->customerPhone ?: null] : null;

        if ($this->customerPhone !== '' && ! Phone::valid($this->customerPhone)) {
            $this->addError('customerPhone', 'Telefone inválido. Use DDD + número.');

            return;
        }

        $ticket = $this->attempt(fn () => app(TicketService::class)->issue(
            $this->unit(), $this->issueServiceId, $priority, $this->user(), $customer,
            notifyPhone: $this->unit()->feature('notify_enabled') ? $this->customerPhone : null,
        ));

        if ($ticket) {
            $this->afterIssue($ticket);
        }
    }

    public function confirmAppointment(int $appointmentId): void
    {
        $appointment = Appointment::where('unit_id', $this->unit()->id)->findOrFail($appointmentId);
        $priority = Priority::active()->orderBy('weight')->first();

        $ticket = $this->attempt(fn () => app(TicketService::class)->issue(
            $this->unit(), $appointment->service_id, $priority, $this->user(), appointment: $appointment,
        ));

        if ($ticket) {
            $this->afterIssue($ticket);
        }
    }

    public function updatedCustomerDocument(): void
    {
        $customer = Customer::where('document', trim($this->customerDocument))->first();
        if ($customer) {
            $this->customerName = $customer->name;
            $this->customerPhone = $this->customerPhone ?: (string) $customer->phone;
        }
    }

    public function reprint(int $ticketId): void
    {
        $this->dispatch('print-ticket', id: $ticketId, url: route('ticket.print', $ticketId));
    }

    /** Impressoras térmicas disponíveis para esta estação. */
    #[Computed]
    public function printers(): Collection
    {
        return $this->unit()->feature('direct_print_enabled')
            ? Printer::active()->where('unit_id', $this->unit()->id)->orderBy('name')->get()
            : collect();
    }

    /** Impressão direta pelo servidor (chamada pela estação que escolheu uma impressora). */
    public function printDirect(int $ticketId, int $printerId): void
    {
        $printer = $this->printers->firstWhere('id', $printerId);
        if (! $printer) {
            $this->toast('Impressora indisponível. Verifique a seleção da estação.', 'error');

            return;
        }

        $ticket = Ticket::where('unit_id', $this->unit()->id)->findOrFail($ticketId);
        $this->attempt(fn () => app(TicketPrinter::class)->print($ticket, $printer));
    }

    private function afterIssue(Ticket $ticket): void
    {
        $this->lastTicketId = $ticket->id;
        $this->showIssue = false;
        $this->reset('customerDocument', 'customerName', 'customerPhone');
        unset($this->counts, $this->appointments, $this->estimates);

        $this->dispatch('ticket-issued', id: $ticket->id, url: route('ticket.print', $ticket), code: $ticket->code());
    }

    private function askCustomer(): bool
    {
        return (bool) session('sga.triage.ask_customer', false);
    }

    public function toggleAskCustomer(): void
    {
        session(['sga.triage.ask_customer' => ! $this->askCustomer()]);
    }

    public function render()
    {
        return view('livewire.triage', [
            'askCustomer' => $this->askCustomer(),
            'hasNormal' => $this->priorities->contains(fn ($p) => $p->weight === 0),
            'hasPriority' => $this->priorities->contains(fn ($p) => $p->weight > 0),
            'appointmentStatus' => AppointmentStatus::class,
        ]);
    }
}
