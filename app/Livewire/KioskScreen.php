<?php

namespace App\Livewire;

use App\Enums\AppointmentStatus;
use App\Enums\ServiceType;
use App\Exceptions\TicketException;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Kiosk;
use App\Models\Priority;
use App\Models\Ticket;
use App\Models\UnitService;
use App\Services\TicketPrinter;
use App\Services\TicketService;
use App\Services\WaitEstimator;
use App\Support\Cpf;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Tela do totem de autoatendimento (pública, identificada pelo public_id do totem). */
#[Layout('layouts.kiosk')]
class KioskScreen extends Component
{
    #[Locked]
    public int $kioskId;

    /** welcome | services | type | priority | document | appointment | done */
    public string $step = 'welcome';

    public ?int $serviceId = null;

    public ?int $priorityId = null;

    public ?int $ticketId = null;

    public ?string $notice = null;

    public ?string $error = null;

    public string $appointmentDocument = '';

    #[Locked]
    public ?string $pendingDocument = null;

    public function mount(Kiosk $kiosk): void
    {
        $this->kioskId = $kiosk->id;
        $kiosk->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    #[Computed]
    public function kiosk(): Kiosk
    {
        return Kiosk::with(['unit', 'printer'])->findOrFail($this->kioskId);
    }

    public function available(): bool
    {
        return $this->kiosk->active && $this->kiosk->unit->active && $this->kiosk->unit->feature('kiosk_enabled');
    }

    #[Computed]
    public function unitServices(): Collection
    {
        $selected = $this->kiosk->services;

        return UnitService::with('service')
            ->where('unit_id', $this->kiosk->unit_id)
            ->active()
            ->when($selected, fn ($q) => $q->whereIn('service_id', $selected))
            ->get()
            ->sortBy('service.name')
            ->values();
    }

    #[Computed]
    public function estimates(): array
    {
        return $this->kiosk->setting('show_eta')
            ? app(WaitEstimator::class)->forServices($this->kiosk->unit, $this->unitServices->pluck('service_id')->all())
            : [];
    }

    #[Computed]
    public function priorities(): Collection
    {
        return Priority::active()->where('weight', '>', 0)->orderBy('weight')->orderBy('name')->get();
    }

    #[Computed]
    public function ticket(): ?Ticket
    {
        return $this->ticketId ? Ticket::with(['service', 'priority', 'unit'])->find($this->ticketId) : null;
    }

    #[Computed]
    public function appointments(): Collection
    {
        $doc = Cpf::digits($this->appointmentDocument);
        $customer = $doc ? Customer::whereIn('document', array_unique([$doc, Cpf::format($doc)]))->first() : null;
        if (! $customer) {
            return collect();
        }

        $unit = $this->kiosk->unit;

        return Appointment::with('service')
            ->where('unit_id', $unit->id)
            ->where('customer_id', $customer->id)
            ->where('status', AppointmentStatus::Scheduled)
            ->whereDate('date', CarbonImmutable::now($unit->timezone())->toDateString())
            ->orderBy('time')
            ->get();
    }

    // ------------------------------------------------------------ navegação

    public function begin(): void
    {
        $this->resetFlow();
        $this->step = 'services';
    }

    public function chooseService(int $serviceId): void
    {
        $us = $this->unitServices->firstWhere('service_id', $serviceId);
        if (! $us) {
            return;
        }

        $this->serviceId = $serviceId;
        $canPriority = $this->kiosk->setting('show_priority') && $this->priorities->isNotEmpty() && $us->type !== ServiceType::NormalOnly;

        if ($us->type === ServiceType::PriorityOnly) {
            $this->choosePreferential();
        } elseif ($canPriority) {
            $this->step = 'type';
        } else {
            $this->chooseNormal();
        }
    }

    public function chooseNormal(): void
    {
        $this->priorityId = Priority::active()->where('weight', 0)->orderBy('name')->value('id');
        $this->afterPriority();
    }

    public function choosePreferential(): void
    {
        if ($this->priorities->count() === 1) {
            $this->choosePriority($this->priorities->first()->id);
        } else {
            $this->step = 'priority';
        }
    }

    public function choosePriority(int $priorityId): void
    {
        $this->priorityId = $this->priorities->firstWhere('id', $priorityId)?->id;
        $this->afterPriority();
    }

    private function afterPriority(): void
    {
        if ($this->kiosk->setting('ask_document')) {
            $this->step = 'document';
        } elseif ($this->askPhone()) {
            $this->step = 'phone';
        } else {
            $this->issue();
        }
    }

    /** Pergunta o WhatsApp só se o totem pedir e a unidade tiver avisos ligados. */
    public function askPhone(): bool
    {
        return $this->kiosk->setting('ask_phone') && $this->kiosk->unit->feature('notify_enabled');
    }

    public function submitPhone(string $phone = ''): void
    {
        $this->error = null;

        if ($phone !== '' && ! Phone::valid($phone)) {
            $this->error = 'Número inválido. Digite DDD + número.';

            return;
        }

        $this->issue($this->pendingDocument, phone: $phone ?: null);
    }

    public function submitDocument(string $document = ''): void
    {
        $digits = Cpf::digits($document);

        if ($digits === '' && $this->kiosk->setting('require_document')) {
            $this->error = 'Informe o CPF para continuar.';

            return;
        }
        if ($digits !== '' && ! Cpf::valid($digits)) {
            $this->error = 'CPF inválido. Confira os números.';

            return;
        }

        if ($this->askPhone()) {
            $this->error = null;
            $this->pendingDocument = $digits ?: null;
            $this->step = 'phone';

            return;
        }

        $this->issue($digits ?: null);
    }

    public function startAppointment(): void
    {
        $this->resetFlow();
        $this->step = 'appointment';
    }

    public function findAppointments(string $document): void
    {
        $this->error = null;
        $this->appointmentDocument = Cpf::digits($document);
        unset($this->appointments);

        if (! Cpf::valid($this->appointmentDocument)) {
            $this->error = 'CPF inválido. Confira os números.';
        } elseif ($this->appointments->isEmpty()) {
            $this->error = 'Nenhum agendamento para hoje neste CPF. Retire uma senha comum ou procure a recepção.';
        }
    }

    public function confirmAppointment(int $appointmentId): void
    {
        $appointment = $this->appointments->firstWhere('id', $appointmentId);
        if (! $appointment) {
            return;
        }

        $this->serviceId = $appointment->service_id;
        $this->priorityId = Priority::active()->where('weight', 0)->orderBy('name')->value('id');
        $this->issue(appointment: $appointment);
    }

    public function back(): void
    {
        $this->error = null;
        $this->step = match ($this->step) {
            'priority' => 'type',
            'document', 'phone' => 'services',
            'type' => 'services',
            default => 'welcome',
        };
    }

    public function resetKiosk(): void
    {
        $this->resetFlow();
        $this->step = 'welcome';
        $this->kiosk->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    private function resetFlow(): void
    {
        $this->reset('serviceId', 'priorityId', 'ticketId', 'notice', 'error', 'appointmentDocument', 'pendingDocument');
    }

    // ------------------------------------------------------------ emissão

    private function issue(?string $document = null, ?Appointment $appointment = null, ?string $phone = null): void
    {
        $this->error = null;
        $kiosk = $this->kiosk;

        if (! $this->available()) {
            $this->error = 'Totem indisponível no momento.';

            return;
        }

        $key = 'kiosk-issue:'.$kiosk->id;
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->error = 'Muitas senhas em pouco tempo. Aguarde um instante.';

            return;
        }
        RateLimiter::hit($key, 60);

        // cliente já cadastrado é vinculado; CPF novo fica registrado na senha
        $customer = $document ? Customer::whereIn('document', array_unique([$document, Cpf::format($document)]))->first() : null;

        try {
            $ticket = app(TicketService::class)->issue(
                $kiosk->unit,
                (int) $this->serviceId,
                Priority::findOrFail($this->priorityId),
                null,
                $customer ? ['document' => $customer->document, 'name' => $customer->name] : null,
                $appointment,
                $document && ! $customer ? ['document' => $document] : null,
                $kiosk,
                $phone,
            );
        } catch (TicketException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->ticketId = $ticket->id;
        $this->step = 'done';
        $this->print($ticket);
    }

    private function print(Ticket $ticket): void
    {
        $kiosk = $this->kiosk;

        if ($kiosk->print_mode === 'printer' && $kiosk->printer && $kiosk->unit->feature('direct_print_enabled')) {
            try {
                app(TicketPrinter::class)->print($ticket, $kiosk->printer);
            } catch (TicketException) {
                $this->notice = 'Não foi possível imprimir. Anote sua senha ou use o QR code.';
            }
        } elseif ($kiosk->print_mode !== 'none') {
            $this->dispatch('kiosk-print', url: route('ticket.print.public', ['ticket' => $ticket, 'hash' => $ticket->hash(), 'autoprint' => 1]));
        }
    }

    public function render()
    {
        return view('livewire.kiosk-screen', [
            'available' => $this->available(),
            'showQr' => $this->kiosk->setting('show_qr') && $this->kiosk->unit->feature('mobile_ticket'),
        ])->title($this->kiosk->name);
    }
}
