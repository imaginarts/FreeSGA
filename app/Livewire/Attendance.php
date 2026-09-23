<?php

namespace App\Livewire;

use App\Enums\QueueType;
use App\Enums\Resolution;
use App\Enums\TicketStatus;
use App\Livewire\Concerns\InteractsWithUnit;
use App\Models\AttendantPause;
use App\Models\Location;
use App\Models\PauseReason;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\UnitService;
use App\Models\User;
use App\Services\PauseService;
use App\Services\QueueService;
use App\Services\SlaService;
use App\Services\TicketService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Atendimento')]
class Attendance extends Component
{
    use InteractsWithUnit;

    // local de atendimento
    public bool $showSettings = false;

    public ?int $locationId = null;

    public ?int $locationNumber = null;

    public string $queueType = 'all';

    // encerramento
    public bool $showFinish = false;

    public array $performed = [];

    public ?string $resolution = null;

    public string $notes = '';

    public bool $redirectOnFinish = false;

    // redirecionamento (erro de triagem ou após encerrar)
    public bool $showRedirect = false;

    public ?int $redirectServiceId = null;

    public ?int $redirectUserId = null;

    // detalhes de senha da fila
    public ?int $detailTicketId = null;

    public bool $showDetail = false;

    // pausa
    public bool $showPause = false;

    public ?int $pauseReasonId = null;

    public string $pauseNotes = '';

    #[Computed]
    public function openPause(): ?AttendantPause
    {
        return $this->unit()->feature('pauses_enabled') ? $this->user()->openPause($this->unit()) : null;
    }

    public function startPause(): void
    {
        $this->validate(['pauseNotes' => ['nullable', 'string', 'max:255']]);

        $done = $this->attempt(fn () => app(PauseService::class)->start($this->unit(), $this->user(), $this->pauseReasonId, $this->pauseNotes), 'Pausa iniciada.');

        if ($done) {
            $this->showPause = false;
            $this->reset('pauseReasonId', 'pauseNotes');
            unset($this->openPause);
        }
    }

    public function endPause(): void
    {
        if ($pause = $this->openPause) {
            app(PauseService::class)->end($pause);
            unset($this->openPause);
            $this->toast('Bem-vindo de volta! Atendimento retomado.');
        }
    }

    public function mount(): void
    {
        $user = $this->user();
        $this->locationId = $user->location_id;
        $this->locationNumber = $user->location_number;
        $this->queueType = $user->queue_type?->value ?? 'all';

        if (! $user->location_id || ! $user->location_number) {
            $this->showSettings = true;
        }
    }

    #[Computed]
    public function current(): ?Ticket
    {
        return app(TicketService::class)->currentTicket($this->unit(), $this->user())
            ?->load(['service', 'priority', 'customer', 'location', 'parent.user']);
    }

    #[Computed]
    public function myServices(): Collection
    {
        return $this->user()->servicesIn($this->unit());
    }

    #[Computed]
    public function queue(): Collection
    {
        return app(QueueService::class)->userQueue($this->unit(), $this->user(), $this->user()->queue_type);
    }

    #[Computed]
    public function performableServices(): Collection
    {
        $ids = $this->myServices->pluck('service_id')->push($this->current?->service_id)->filter()->unique();

        return Service::with(['children' => fn ($q) => $q->where('active', true)])->whereIn('id', $ids)->orderBy('name')->get();
    }

    /** Serviços da unidade para os quais é possível redirecionar. */
    #[Computed]
    public function redirectServices(): Collection
    {
        return UnitService::with('service')->where('unit_id', $this->unit()->id)->active()->get()
            ->reject(fn ($us) => $us->service_id === $this->current?->service_id)
            ->sortBy('service.name');
    }

    #[Computed]
    public function redirectUsers(): Collection
    {
        if (! $this->redirectServiceId) {
            return collect();
        }

        return User::where('active', true)
            ->whereHas('serviceUsers', fn ($q) => $q->where('unit_id', $this->unit()->id)->where('service_id', $this->redirectServiceId))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function detail(): ?Ticket
    {
        return $this->detailTicketId
            ? Ticket::with(['service', 'priority', 'customer'])->where('unit_id', $this->unit()->id)->find($this->detailTicketId)
            : null;
    }

    // ------------------------------------------------------------ ações

    public function openDetail(int $ticketId): void
    {
        $this->detailTicketId = $ticketId;
        $this->showDetail = true;
    }

    public function saveSettings(): void
    {
        $data = $this->validate([
            'locationId' => ['required', 'exists:locations,id'],
            'locationNumber' => ['required', 'integer', 'min:1', 'max:999'],
            'queueType' => ['required', Rule::enum(QueueType::class)],
        ], [], ['locationId' => 'local', 'locationNumber' => 'número', 'queueType' => 'tipo de atendimento']);

        $user = $this->user();
        $user->location_id = $data['locationId'];
        $user->location_number = $data['locationNumber'];
        if ($user->behavior('change_queue_type')) {
            $user->queue_type = QueueType::from($data['queueType']);
        }
        $user->save();

        $this->showSettings = false;
        $this->toast('Local de atendimento atualizado.');
    }

    public function callNext(?int $serviceId = null): void
    {
        if ($serviceId && ! $this->user()->behavior('call_by_service')) {
            return;
        }

        $this->attempt(fn () => app(TicketService::class)->callNext($this->unit(), $this->user(), $serviceId));
        $this->refreshState();
    }

    public function callTicket(int $ticketId): void
    {
        if (! $this->user()->behavior('call_out_of_order')) {
            return;
        }

        $ticket = Ticket::where('unit_id', $this->unit()->id)->findOrFail($ticketId);
        $this->attempt(fn () => app(TicketService::class)->callTicket($ticket, $this->user()));
        $this->showDetail = false;
        $this->refreshState();
    }

    public function recall(): void
    {
        $this->withCurrent(fn ($t, $s) => $s->recall($t, $this->user()), 'Senha chamada novamente.');
    }

    public function start(): void
    {
        $this->withCurrent(fn ($t, $s) => $s->start($t, $this->user()));
    }

    public function noShow(): void
    {
        $this->withCurrent(fn ($t, $s) => $s->noShow($t, $this->user()), 'Registrado não comparecimento.');
    }

    public function openFinish(): void
    {
        $this->reset('resolution', 'notes', 'redirectOnFinish', 'redirectServiceId', 'redirectUserId');
        $this->resetErrorBag();

        // pré-seleciona quando só existe uma opção possível
        $services = $this->performableServices;
        $this->performed = [];
        if ($services->count() === 1) {
            $only = $services->first();
            $this->performed = $only->children->count() === 1 ? [(string) $only->children->first()->id] : ($only->children->isEmpty() ? [(string) $only->id] : []);
        }

        $this->showFinish = true;
    }

    public function finish(): void
    {
        $this->validate([
            'performed' => ['required', 'array', 'min:1'],
            'resolution' => ['nullable', Rule::enum(Resolution::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'redirectServiceId' => [$this->redirectOnFinish ? 'required' : 'nullable', 'integer'],
        ], ['performed.required' => 'Selecione ao menos um serviço realizado.'], ['redirectServiceId' => 'serviço de destino']);

        $done = $this->withCurrent(fn ($t, $s) => $s->finish(
            $t, $this->user(), $this->performed,
            $this->resolution ? Resolution::from($this->resolution) : null,
            $this->notes ?: null,
            $this->redirectOnFinish ? $this->redirectServiceId : null,
            $this->redirectOnFinish ? $this->redirectUserId : null,
        ), 'Atendimento encerrado.');

        if ($done) {
            $this->showFinish = false;
        }
    }

    public function openRedirect(): void
    {
        $this->reset('redirectServiceId', 'redirectUserId');
        $this->showRedirect = true;
    }

    public function redirectTicket(): void
    {
        $this->validate(['redirectServiceId' => ['required', 'integer']], [], ['redirectServiceId' => 'serviço']);

        $done = $this->withCurrent(fn ($t, $s) => $s->redirect($t, $this->user(), $this->redirectServiceId, $this->redirectUserId), 'Senha redirecionada.');

        if ($done) {
            $this->showRedirect = false;
        }
    }

    private function withCurrent(callable $action, ?string $success = null): mixed
    {
        $ticket = $this->current;
        if (! $ticket) {
            $this->toast('Nenhum atendimento em andamento.', 'error');

            return null;
        }

        $result = $this->attempt(fn () => $action($ticket, app(TicketService::class)), $success);
        $this->refreshState();

        return $result;
    }

    private function refreshState(): void
    {
        unset($this->current, $this->queue);
    }

    public function render()
    {
        $user = $this->user();

        return view('livewire.attendance', [
            'locations' => Location::orderBy('name')->get(),
            'queueTypes' => QueueType::cases(),
            'resolutions' => Resolution::cases(),
            'statuses' => TicketStatus::class,
            'canChangeType' => $user->behavior('change_queue_type'),
            'callByService' => $user->behavior('call_by_service'),
            'callOutOfOrder' => $user->behavior('call_out_of_order'),
            'pausesEnabled' => $this->unit()->feature('pauses_enabled'),
            'pauseReasons' => PauseReason::where('active', true)->orderBy('name')->get(),
            'requireReason' => $this->unit()->feature('pause_require_reason'),
            'sla' => $this->unit()->feature('sla_attendance_highlight') ? app(SlaService::class) : null,
        ]);
    }
}
