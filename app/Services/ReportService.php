<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Allocation;
use App\Models\AttendantPause;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceUser;
use App\Models\SurveyResponse;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\UnitService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Consultas de estatísticas e relatórios (incluem senhas arquivadas). */
class ReportService
{
    public const REPORTS = [
        'services-performed' => ['Serviços realizados', true],
        'finished' => ['Atendimentos encerrados', true],
        'all' => ['Atendimentos em todos os status', true],
        'attendants' => ['Tempos médios por atendente', true],
        'daily' => ['Movimento diário', true],
        'sla' => ['Cumprimento de metas de espera', true],
        'pauses' => ['Pausas por atendente', true],
        'satisfaction' => ['Pesquisa de satisfação', true],
        'unit-services' => ['Serviços disponíveis na unidade', false],
        'services' => ['Catálogo global de serviços', false],
        'allocations' => ['Lotações da unidade', false],
        'roles' => ['Perfis e módulos', false],
    ];

    /** Converte datas locais (Y-m-d) da unidade no intervalo UTC. */
    public function range(Unit $unit, string $start, string $end): array
    {
        $tz = $unit->timezone();

        return [
            CarbonImmutable::parse($start, $tz)->startOfDay()->utc(),
            CarbonImmutable::parse($end, $tz)->endOfDay()->utc(),
        ];
    }

    public function tickets(Unit $unit, array $range, ?int $userId = null): Builder
    {
        return Ticket::query()
            ->where('tickets.unit_id', $unit->id)
            ->whereBetween('tickets.arrived_at', $range)
            ->when($userId, fn ($q) => $q->where('tickets.user_id', $userId));
    }

    public function byStatus(Unit $unit, array $range, ?int $userId = null): Collection
    {
        $counts = $this->tickets($unit, $range, $userId)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(TicketStatus::cases())->mapWithKeys(fn ($s) => [$s->label() => (int) ($counts[$s->value] ?? 0)]);
    }

    public function byService(Unit $unit, array $range, ?int $userId = null): Collection
    {
        return $this->tickets($unit, $range, $userId)
            ->join('services', 'services.id', '=', 'tickets.service_id')
            ->where('tickets.status', TicketStatus::Finished)
            ->selectRaw('services.name, count(*) as total')
            ->groupBy('services.name')->orderByDesc('total')
            ->pluck('total', 'name');
    }

    public function averages(Unit $unit, array $range, ?int $userId = null): array
    {
        $row = $this->tickets($unit, $range, $userId)
            ->selectRaw('avg(wait_time) as wait, avg(travel_time) as travel, avg(service_time) as service, avg(total_time) as total')
            ->first();

        return [
            'Espera' => (int) $row->wait,
            'Deslocamento' => (int) $row->travel,
            'Atendimento' => (int) $row->service,
            'Permanência' => (int) $row->total,
        ];
    }

    public function build(string $report, Unit $unit, array $range, ?int $userId): mixed
    {
        return match ($report) {
            'services-performed' => DB::table('ticket_services')
                ->join('tickets', 'tickets.id', '=', 'ticket_services.ticket_id')
                ->join('services', 'services.id', '=', 'ticket_services.service_id')
                ->where('tickets.unit_id', $unit->id)
                ->whereBetween('tickets.arrived_at', $range)
                ->when($userId, fn ($q) => $q->where('tickets.user_id', $userId))
                ->selectRaw('services.name, sum(ticket_services.weight) as total')
                ->groupBy('services.name')->orderBy('services.name')->get(),

            'finished' => $this->tickets($unit, $range, $userId)
                ->with(['service', 'user', 'unit'])
                ->where('status', TicketStatus::Finished)
                ->orderBy('id')->limit(5000)->get(),

            'all' => $this->tickets($unit, $range, $userId)
                ->with(['service', 'user', 'customer', 'unit'])
                ->orderBy('id')->limit(5000)->get(),

            'attendants' => $this->tickets($unit, $range)
                ->join('users', 'users.id', '=', 'tickets.user_id')
                ->whereNotNull('tickets.finished_at')
                ->selectRaw("users.name, users.last_name, count(*) as total, avg(wait_time) as wait, avg(travel_time) as travel, avg(service_time) as service, avg(total_time) as total_time, sum(case when tickets.status = 'no_show' then 1 else 0 end) as no_show")
                ->groupBy('users.id', 'users.name', 'users.last_name')
                ->orderBy('users.name')->get(),

            'daily' => $this->daily($unit, $range, $userId),

            'sla' => $this->sla($unit, $range, $userId),

            'satisfaction' => $this->satisfaction($unit, $range, $userId),

            'pauses' => AttendantPause::with('user')
                ->where('unit_id', $unit->id)
                ->whereBetween('started_at', $range)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->get()
                ->groupBy('user_id')
                ->map(fn ($items) => [
                    'user' => $items->first()->user,
                    'count' => $items->count(),
                    'total' => $items->sum(fn ($p) => $p->elapsed()),
                    'exceeded' => $items->filter->exceeded()->count(),
                    'by_reason' => $items->groupBy(fn ($p) => $p->reason ?? 'Sem motivo')
                        ->map(fn ($g) => ['count' => $g->count(), 'total' => $g->sum(fn ($p) => $p->elapsed())])
                        ->sortKeys(),
                ])
                ->sortBy(fn ($r) => $r['user']?->name)
                ->values(),

            'unit-services' => UnitService::with(['service.children', 'department'])
                ->where('unit_id', $unit->id)->where('active', true)->get()->sortBy('service.name'),

            'services' => Service::main()->with('children')->orderBy('name')->get(),

            'allocations' => Allocation::with(['user', 'role'])->where('unit_id', $unit->id)->get()
                ->sortBy('user.name')
                ->map(fn ($a) => [
                    'allocation' => $a,
                    'services' => ServiceUser::with('service')->where('unit_id', $unit->id)->where('user_id', $a->user_id)->get()->pluck('service.name'),
                ]),

            'roles' => Role::orderBy('name')->get(),
        };
    }

    /** Respostas agrupadas por atendente e por serviço, com NPS (0-10) ou % de satisfeitos (1-5). */
    private function satisfaction(Unit $unit, array $range, ?int $userId): array
    {
        $responses = SurveyResponse::with(['user', 'service', 'ticket'])
            ->where('unit_id', $unit->id)
            ->whereBetween('created_at', $range)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->get();

        $summarize = fn (Collection $items) => [
            'total' => $items->count(),
            'avg' => round((float) $items->avg('score'), 1),
            'positive' => $items->filter->positive()->count(),
            'negative' => $items->filter->negative()->count(),
            // NPS = % promotores - % detratores; CSAT = % de notas 4 e 5
            'index' => $items->isEmpty() ? null : (int) round(
                $items->first()->scale === 'nps'
                    ? ($items->filter->positive()->count() - $items->filter->negative()->count()) / $items->count() * 100
                    : $items->filter->positive()->count() / $items->count() * 100
            ),
        ];

        return [
            'scale' => $responses->first()->scale ?? $unit->setting('survey_scale'),
            'overall' => $summarize($responses),
            'attendants' => $responses->groupBy(fn ($r) => $r->user?->fullName() ?? 'Sem atendente')->map($summarize)->sortKeys(),
            'services' => $responses->groupBy(fn ($r) => $r->service->name)->map($summarize)->sortKeys(),
            'comments' => $responses->whereNotNull('comment')->sortByDesc('created_at')->take(100),
        ];
    }

    /** Por serviço: senhas chamadas, quantas dentro da meta e espera média. */
    private function sla(Unit $unit, array $range, ?int $userId): Collection
    {
        $sla = app(SlaService::class);

        return $this->tickets($unit, $range, $userId)
            ->with('service')
            ->whereNotNull('wait_time')
            ->get(['id', 'service_id', 'wait_time'])
            ->groupBy('service_id')
            ->map(function ($items, $serviceId) use ($sla, $unit) {
                $target = $sla->target($unit, $serviceId);
                $within = $target ? $items->filter(fn ($t) => $t->wait_time <= $target)->count() : null;

                return [
                    'service' => $items->first()->service->name,
                    'target' => $target,
                    'total' => $items->count(),
                    'within' => $within,
                    'percent' => $target ? round($within / $items->count() * 100, 1) : null,
                    'avg' => (int) $items->avg('wait_time'),
                    'max' => (int) $items->max('wait_time'),
                ];
            })
            ->sortBy('service')
            ->values();
    }

    private function daily(Unit $unit, array $range, ?int $userId): Collection
    {
        $tz = $unit->timezone();

        return $this->tickets($unit, $range, $userId)
            ->get(['arrived_at', 'status', 'wait_time', 'service_time'])
            ->groupBy(fn ($t) => $t->arrived_at->copy()->setTimezone($tz)->format('Y-m-d'))
            ->sortKeys()
            ->map(fn ($day) => [
                'total' => $day->count(),
                'finished' => $day->where('status', TicketStatus::Finished)->count(),
                'no_show' => $day->where('status', TicketStatus::NoShow)->count(),
                'cancelled' => $day->where('status', TicketStatus::Cancelled)->count(),
                'wait' => (int) $day->avg('wait_time'),
                'service' => (int) $day->avg('service_time'),
            ]);
    }
}
