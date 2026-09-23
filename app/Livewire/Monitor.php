<?php

namespace App\Livewire;

use App\Enums\TicketStatus;
use App\Livewire\Concerns\InteractsWithUnit;
use App\Models\AttendantPause;
use App\Models\Priority;
use App\Models\Ticket;
use App\Models\UnitService;
use App\Models\User;
use App\Services\QueueService;
use App\Services\SlaService;
use App\Services\TicketService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Monitor')]
class Monitor extends Component
{
    use InteractsWithUnit;

    public ?int $ticketId = null;

    public bool $showTicket = false;

    public bool $showTransfer = false;

    public ?int $transferServiceId = null;

    public ?int $transferPriorityId = null;

    public string $search = '';

    #[Computed]
    public function queue(): Collection
    {
        return app(QueueService::class)->unitQueue($this->unit());
    }

    #[Computed]
    public function unitServices(): Collection
    {
        return UnitService::with('service')->where('unit_id', $this->unit()->id)->active()->get()->sortBy('service.name');
    }

    /** Senhas em atendimento agora, por atendente. */
    #[Computed]
    public function inProgress(): Collection
    {
        return Ticket::with(['service', 'priority', 'user', 'location'])
            ->current()
            ->where('unit_id', $this->unit()->id)
            ->whereIn('status', TicketStatus::inProgress())
            ->orderBy('called_at')
            ->get();
    }

    /** Estado de meta (ok/warning/breach) de cada senha aguardando. */
    #[Computed]
    public function slaStates(): array
    {
        $unit = $this->unit();
        $sla = app(SlaService::class);

        return $sla->enabled($unit)
            ? $this->queue->mapWithKeys(fn ($t) => [$t->id => $sla->state($t, $unit)])->all()
            : [];
    }

    /** Indicadores de meta do dia. */
    #[Computed]
    public function slaSummary(): ?array
    {
        $unit = $this->unit();
        $sla = app(SlaService::class);
        if (! $sla->enabled($unit)) {
            return null;
        }

        $called = Ticket::where('unit_id', $unit->id)
            ->where('arrived_at', '>=', CarbonImmutable::now($unit->timezone())->startOfDay()->utc())
            ->whereNotNull('wait_time')
            ->get(['id', 'unit_id', 'service_id', 'wait_time']);

        $within = $called->filter(fn ($t) => ($target = $sla->target($unit, $t->service_id)) === null || $t->wait_time <= $target)->count();

        return [
            'breach' => collect($this->slaStates)->filter(fn ($s) => $s === SlaService::BREACH)->count(),
            'longest' => $this->queue->isEmpty() ? null : (int) $this->queue->min('arrived_at')->diffInSeconds(now(), true),
            'percent' => $called->isEmpty() ? null : (int) round($within / $called->count() * 100),
        ];
    }

    /** Situação de cada atendente da unidade: em atendimento, em pausa ou disponível. */
    #[Computed]
    public function attendants(): Collection
    {
        $unit = $this->unit();
        $since = now()->subMinutes(30);

        $pauses = $unit->feature('pauses_enabled')
            ? AttendantPause::open()->where('unit_id', $unit->id)->get()->keyBy('user_id')
            : collect();

        $recent = Ticket::where('unit_id', $unit->id)
            ->where(fn ($q) => $q->where('called_at', '>=', $since)->orWhere('finished_at', '>=', $since))
            ->distinct()->pluck('user_id');

        $ids = $this->inProgress->pluck('user_id')->merge($pauses->keys())->merge($recent)->filter()->unique();

        return User::whereIn('id', $ids)->orderBy('name')->get()->map(function (User $u) use ($pauses) {
            $ticket = $this->inProgress->firstWhere('user_id', $u->id);
            $pause = $pauses[$u->id] ?? null;

            return [
                'user' => $u,
                'status' => $ticket ? 'attending' : ($pause ? 'paused' : 'available'),
                'ticket' => $ticket,
                'pause' => $pause,
                'exceeded' => $pause && $this->unit()->feature('pause_alert_exceeded') && $pause->exceeded(),
            ];
        });
    }

    #[Computed]
    public function ticket(): ?Ticket
    {
        return $this->ticketId
            ? Ticket::with(['service', 'priority', 'customer', 'user', 'triageUser'])->where('unit_id', $this->unit()->id)->find($this->ticketId)
            : null;
    }

    #[Computed]
    public function searchResults(): Collection
    {
        if (trim($this->search) === '') {
            return collect();
        }

        preg_match('/^\s*([a-zA-Z]*)\s*(\d*)\s*$/', $this->search, $m);

        return Ticket::with(['service', 'user'])
            ->current()
            ->where('unit_id', $this->unit()->id)
            ->when($m[1] ?? '', fn ($q, $prefix) => $q->where('prefix', strtoupper($prefix)))
            ->when((int) ($m[2] ?? 0), fn ($q, $number) => $q->where('number', $number))
            ->latest('id')
            ->limit(50)
            ->get();
    }

    public function open(int $id): void
    {
        $this->ticketId = $id;
        unset($this->ticket);
        $this->showTicket = true;
    }

    public function openTransfer(): void
    {
        $this->transferServiceId = $this->ticket?->service_id;
        $this->transferPriorityId = $this->ticket?->priority_id;
        $this->showTransfer = true;
    }

    public function transfer(): void
    {
        $this->validate([
            'transferServiceId' => ['required', 'integer'],
            'transferPriorityId' => ['required', 'exists:priorities,id'],
        ], [], ['transferServiceId' => 'serviço', 'transferPriorityId' => 'prioridade']);

        $done = $this->attempt(fn () => app(TicketService::class)->transfer(
            $this->ticket, $this->transferServiceId, Priority::findOrFail($this->transferPriorityId),
        ), 'Senha transferida.');

        if ($done) {
            $this->showTransfer = false;
            $this->showTicket = false;
        }
    }

    public function cancel(): void
    {
        if ($this->attempt(fn () => app(TicketService::class)->cancel($this->ticket), 'Senha cancelada.')) {
            $this->showTicket = false;
        }
    }

    public function reactivate(): void
    {
        if ($this->attempt(fn () => app(TicketService::class)->reactivate($this->ticket), 'Senha reativada.')) {
            $this->showTicket = false;
        }
    }

    public function render()
    {
        return view('livewire.monitor', [
            'priorities' => Priority::active()->orderBy('weight')->get(),
            'statuses' => TicketStatus::class,
            'slaSound' => $this->unit()->feature('sla_monitor_sound'),
        ]);
    }
}
