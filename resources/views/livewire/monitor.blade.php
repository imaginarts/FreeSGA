@php($queue = $this->queue)
@php($states = $this->slaStates)
@php($summary = $this->slaSummary)
<div wire:poll.15s
     x-data="{
        last: null,
        sound: @js($slaSound),
        beep() {
            try {
                const ctx = new AudioContext();
                [0, .3].forEach(t => { const o = ctx.createOscillator(), g = ctx.createGain(); o.frequency.value = 520; g.gain.value = .2; o.connect(g); g.connect(ctx.destination); o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + .2); });
            } catch (e) {}
        },
        check(n) {
            if (this.last !== null && n > this.last) {
                if (this.sound) this.beep();
                $dispatch('toast', { type: 'error', message: n + ' senha(s) acima da meta de espera' });
            }
            this.last = n;
        },
     }">
    @if ($summary)
        {{-- recriado quando o número de senhas fora da meta muda --}}
        <span class="hidden" wire:key="breach-{{ $summary['breach'] }}" x-init="check({{ $summary['breach'] }})"></span>
    @endif

    <x-page-header title="Monitor" :subtitle="$queue->count().' senha(s) aguardando · '.$this->inProgress->count().' em atendimento'">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-slate-400" />
            <input class="input w-56 pl-9" placeholder="Buscar senha (ex.: A12)" wire:model.live.debounce.300ms="search">
        </div>
    </x-page-header>

    @if ($summary)
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="card card-body">
                <p class="text-sm text-slate-500">Aguardando</p>
                <p class="mt-1 font-mono text-3xl font-semibold">{{ $queue->count() }}</p>
            </div>
            <div @class(['card card-body', 'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/40' => $summary['breach'] > 0])>
                <p class="text-sm text-slate-500">Acima da meta</p>
                <p @class(['mt-1 font-mono text-3xl font-semibold', 'text-red-600' => $summary['breach'] > 0])>{{ $summary['breach'] }}</p>
            </div>
            <div class="card card-body">
                <p class="text-sm text-slate-500">Maior espera agora</p>
                <p class="mt-1 font-mono text-3xl font-semibold">{{ $summary['longest'] === null ? '—' : \App\Models\Ticket::formatSeconds($summary['longest']) }}</p>
            </div>
            <div class="card card-body">
                <p class="text-sm text-slate-500">Dentro da meta hoje</p>
                <p @class(['mt-1 font-mono text-3xl font-semibold',
                    'text-emerald-600' => ($summary['percent'] ?? 100) >= 90,
                    'text-amber-600' => ($summary['percent'] ?? 100) < 90 && ($summary['percent'] ?? 100) >= 70,
                    'text-red-600' => ($summary['percent'] ?? 100) < 70])>{{ $summary['percent'] === null ? '—' : $summary['percent'].'%' }}</p>
            </div>
        </div>
    @endif

    @if ($search !== '')
        <div class="card mb-6 overflow-x-auto">
            <table class="table">
                <thead><tr><th>Senha</th><th>Serviço</th><th>Chegada</th><th>Atendente</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($this->searchResults as $t)
                    <tr class="cursor-pointer" wire:click="open({{ $t->id }})" wire:key="sr-{{ $t->id }}">
                        <td class="font-mono font-semibold">{{ $t->code() }}</td>
                        <td>{{ $t->service->name }}</td>
                        <td>{{ $t->localTime($t->arrived_at, 'd/m H:i') }}</td>
                        <td>{{ $t->user?->login ?? '—' }}</td>
                        <td><x-status-badge :status="$t->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-500">Nenhuma senha encontrada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <div class="space-y-4">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Todos os serviços</h2>
                    <div class="flex gap-1.5 text-xs">
                        <span class="badge bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">Normal {{ $queue->filter(fn ($t) => $t->priority->weight === 0)->count() }}</span>
                        <span class="badge bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Prioridade {{ $queue->filter(fn ($t) => $t->priority->weight > 0)->count() }}</span>
                    </div>
                </div>
                <div class="card-body flex flex-wrap gap-2">
                    @forelse ($queue as $t)
                        @include('livewire.partials.monitor-chip', ['t' => $t, 'state' => $states[$t->id] ?? null, 'key' => 'all'])
                    @empty
                        <p class="text-sm text-slate-500">Ninguém aguardando atendimento no momento.</p>
                    @endforelse
                </div>
                @if ($summary)
                    <div class="flex gap-4 border-t border-slate-100 px-5 py-2 text-xs text-slate-500 dark:border-slate-800">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-amber-400"></span> Próxima da meta</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-red-500"></span> Acima da meta</span>
                    </div>
                @endif
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($this->unitServices as $us)
                    @php($items = $queue->where('service_id', $us->service_id))
                    @continue($items->isEmpty())
                    <div class="card" wire:key="svc-{{ $us->id }}">
                        <div class="card-header">
                            <h3 class="card-title"><span class="font-mono text-slate-400">{{ $us->prefix }}</span> {{ $us->service->name }}</h3>
                            <span class="flex items-center gap-2">
                                @if ($summary)<span class="text-xs text-slate-400">meta {{ $us->wait_target ?: auth()->user()->currentUnit->setting('sla_default_target') }} min</span>@endif
                                <span class="badge bg-slate-100 dark:bg-slate-800">{{ $items->count() }}</span>
                            </span>
                        </div>
                        <div class="card-body flex flex-wrap gap-2">
                            @foreach ($items as $t)
                                @include('livewire.partials.monitor-chip', ['t' => $t, 'state' => $states[$t->id] ?? null, 'key' => 's'])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <aside class="card h-fit">
            <div class="card-header"><h2 class="card-title">Atendentes</h2><span class="text-xs text-slate-400">ativos nos últimos 30 min</span></div>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($this->attendants as $a)
                    <li class="flex items-center gap-3 px-5 py-3" wire:key="at-{{ $a['user']->id }}">
                        <span @class(['size-2.5 shrink-0 rounded-full',
                            'bg-blue-500' => $a['status'] === 'attending',
                            'bg-amber-500' => $a['status'] === 'paused' && ! $a['exceeded'],
                            'bg-red-500 animate-pulse' => $a['exceeded'],
                            'bg-emerald-500' => $a['status'] === 'available'])></span>
                        <span class="min-w-0 flex-1 text-sm">
                            <span class="block truncate font-medium">{{ $a['user']->fullName() }}</span>
                            <span @class(['block text-xs', 'text-red-600 font-medium' => $a['exceeded'], 'text-slate-500' => ! $a['exceeded']])>
                                @if ($a['status'] === 'attending')
                                    {{ $a['ticket']->location?->name }} {{ $a['ticket']->location_number }} · {{ $a['ticket']->service->name }}
                                @elseif ($a['status'] === 'paused')
                                    Pausa{{ $a['pause']->reason ? ': '.$a['pause']->reason : '' }} há {{ intdiv($a['pause']->elapsed(), 60) }} min{{ $a['exceeded'] ? ' (acima do limite)' : '' }}
                                @else
                                    Disponível
                                @endif
                            </span>
                        </span>
                        @if ($a['ticket'])
                            <span class="font-mono font-bold" style="color: {{ $a['ticket']->priority->color }}">{{ $a['ticket']->code() }}</span>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-500">Nenhum atendente ativo.</li>
                @endforelse
            </ul>
        </aside>
    </div>

    <x-modal wire:model="showTicket" title="Detalhes da senha">
        @if ($t = $this->ticket)
            <div class="mb-4 flex items-center justify-between">
                <span class="font-mono text-4xl font-bold" style="color: {{ $t->priority->color }}">{{ $t->code() }}</span>
                <x-status-badge :status="$t->status" />
            </div>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Serviço</dt><dd>{{ $t->service->name }}</dd></div>
                <div><dt class="text-slate-500">Prioridade</dt><dd>{{ $t->priority->name }}</dd></div>
                <div><dt class="text-slate-500">Chegada</dt><dd>{{ $t->localTime($t->arrived_at) }}</dd></div>
                <div><dt class="text-slate-500">Espera</dt><dd>{{ \App\Models\Ticket::formatSeconds($t->wait_time ?? (int) $t->arrived_at->diffInSeconds(now())) }}</dd></div>
                <div><dt class="text-slate-500">Início</dt><dd>{{ $t->localTime($t->started_at) ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Fim</dt><dd>{{ $t->localTime($t->finished_at) ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Triagem</dt><dd>{{ $t->triageUser?->login ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Atendente</dt><dd>{{ $t->user?->login ?? '—' }}</dd></div>
                @if ($t->customer)<div class="col-span-2"><dt class="text-slate-500">Cliente</dt><dd>{{ $t->customer->name }} ({{ \App\Support\Privacy::document($t->customer->document) }})</dd></div>@endif
            </dl>
            <div class="mt-5 flex justify-end gap-2">
                @if ($t->status === $statuses::Issued)
                    <button class="btn btn-secondary" wire:click="openTransfer"><x-icon name="arrows" class="size-4" /> Transferir</button>
                    <button class="btn btn-danger" wire:click="cancel" wire:confirm="Deseja realmente cancelar esta senha?"><x-icon name="x" class="size-4" /> Cancelar</button>
                @elseif (in_array($t->status, [$statuses::Cancelled, $statuses::NoShow]))
                    <button class="btn btn-primary" wire:click="reactivate" wire:confirm="Deseja reativar esta senha?"><x-icon name="refresh" class="size-4" /> Reativar</button>
                @endif
            </div>
        @endif
    </x-modal>

    <x-modal wire:model="showTransfer" title="Transferir senha">
        <form wire:submit="transfer" class="space-y-4">
            <x-field label="Serviço" error="transferServiceId">
                <select class="input" wire:model="transferServiceId">
                    @foreach ($this->unitServices as $us)<option value="{{ $us->service_id }}">{{ $us->prefix }} - {{ $us->service->name }}</option>@endforeach
                </select>
            </x-field>
            <x-field label="Prioridade" error="transferPriorityId">
                <select class="input" wire:model="transferPriorityId">
                    @foreach ($priorities as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                </select>
            </x-field>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Transferir</button>
            </div>
        </form>
    </x-modal>
</div>
