@php($current = $this->current)
@php($queueCount = $this->queue->count())
<div wire:poll.20s
     x-data="{
        last: null,
        beep() {
            try {
                const ctx = new AudioContext(), o = ctx.createOscillator(), g = ctx.createGain();
                o.frequency.value = 740; g.gain.value = .15; o.connect(g); g.connect(ctx.destination);
                o.start(); o.stop(ctx.currentTime + .25);
            } catch (e) {}
        },
        check(n) {
            document.title = (n ? '(' + n + ') ' : '') + 'Atendimento';
            if (this.last === 0 && n > 0) {
                this.beep();
                if (window.Notification && Notification.permission === 'granted') new Notification('Nova senha na fila');
            }
            this.last = n;
        },
     }"
     x-init="if (window.Notification && Notification.permission === 'default') Notification.requestPermission()">
    {{-- recriado quando a contagem muda, disparando o aviso sonoro --}}
    <span class="hidden" wire:key="qc-{{ $queueCount }}" x-init="check({{ $queueCount }})"></span>

    <x-page-header title="Atendimento">
        @if ($pausesEnabled && ! $current && ! $this->openPause)
            <button class="btn btn-secondary" wire:click="$set('showPause', true)"><x-icon name="clock" class="size-4" /> Pausar</button>
        @endif
        <button class="btn btn-secondary" wire:click="$set('showSettings', true)">
            <x-icon name="map-pin" class="size-4" />
            @if (auth()->user()->location)
                {{ auth()->user()->location->name }} {{ str_pad(auth()->user()->location_number, 2, '0', STR_PAD_LEFT) }}
            @else
                Definir local
            @endif
            <span class="text-slate-400">·</span> {{ auth()->user()->queue_type?->label() }}
        </button>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
        {{-- Atendimento atual --}}
        <section class="card overflow-hidden">
            @if ($pause = $this->openPause)
                <div class="flex flex-col items-center justify-center px-5 py-16 text-center"
                     x-data="{ start: {{ $pause->started_at->getTimestamp() }}, max: {{ ($pause->max_minutes ?? 0) * 60 }}, now: Math.floor(Date.now() / 1000) }"
                     x-init="setInterval(() => now = Math.floor(Date.now() / 1000), 1000)">
                    <span class="grid size-16 place-items-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-900/40"><x-icon name="clock" class="size-8" /></span>
                    <p class="mt-4 text-lg font-semibold">Em pausa{{ $pause->reason ? ' · '.$pause->reason : '' }}</p>
                    <p class="font-mono text-5xl font-bold tabular-nums" :class="max && now - start > max ? 'text-red-600' : ''"
                       x-text="[Math.floor((now - start) / 3600), Math.floor((now - start) % 3600 / 60), (now - start) % 60].map(n => String(Math.max(0, n)).padStart(2, '0')).join(':')"></p>
                    @if ($pause->max_minutes)
                        <p class="text-sm text-slate-500" :class="now - start > max ? '!text-red-600 font-medium' : ''">Tempo máximo: {{ $pause->max_minutes }} min</p>
                    @endif
                    @if ($pause->notes)<p class="mt-1 text-sm text-slate-500">{{ $pause->notes }}</p>@endif
                    <button class="btn btn-primary btn-lg mt-6 min-w-56" wire:click="endPause"><x-icon name="play" class="size-5" /> Retomar atendimento</button>
                </div>
            @elseif ($current)
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3 dark:border-slate-800">
                    <x-status-badge :status="$current->status" />
                    <span class="text-sm text-slate-500">Chamada às {{ $current->localTime($current->called_at, 'H:i:s') }}</span>
                </div>
                <div class="px-5 py-8 text-center">
                    <p class="text-sm font-medium tracking-wide text-slate-500 uppercase">Senha</p>
                    <p class="font-mono text-7xl font-bold tracking-wider sm:text-8xl" style="color: {{ $current->priority->color }}">{{ $current->code() }}</p>
                    <p class="mt-3 text-lg font-semibold">{{ $current->service->name }}</p>
                    <p class="text-slate-500">{{ $current->priority->name }} · espera de {{ \App\Models\Ticket::formatSeconds($current->wait_time) }}</p>
                    @if ($current->customer)
                        <p class="mt-3 inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-sm dark:bg-slate-800"><x-icon name="id-card" class="size-4" /> {{ $current->customer->name }} · {{ \App\Support\Privacy::document($current->customer->document) }}</p>
                    @endif
                    @if ($current->parent)
                        <p class="mt-2 text-xs text-purple-600">Redirecionada por {{ $current->parent->user?->name ?? '—' }}</p>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-2 border-t border-slate-200 bg-slate-50 p-4 sm:grid-cols-4 dark:border-slate-800 dark:bg-slate-900/60">
                    @if ($current->status === $statuses::Called)
                        <button class="btn btn-secondary btn-lg" wire:click="recall" wire:loading.attr="disabled"><x-icon name="megaphone" class="size-4" /> Rechamar</button>
                        <button class="btn btn-success btn-lg" wire:click="start" wire:loading.attr="disabled"><x-icon name="play" class="size-4" /> Iniciar</button>
                        <button class="btn btn-warning btn-lg col-span-2" wire:click="noShow" wire:confirm="Confirmar que o cliente não compareceu?" wire:loading.attr="disabled"><x-icon name="user-x" class="size-4" /> Não compareceu</button>
                    @else
                        <button class="btn btn-primary btn-lg col-span-2" wire:click="openFinish"><x-icon name="stop" class="size-4" /> Encerrar</button>
                        <button class="btn btn-secondary btn-lg col-span-2" wire:click="openRedirect"><x-icon name="redo" class="size-4" /> Erro de triagem / Redirecionar</button>
                    @endif
                </div>
            @else
                <div class="flex flex-col items-center justify-center px-5 py-16 text-center">
                    <span class="grid size-16 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-900/40"><x-icon name="headset" class="size-8" /></span>
                    <p class="mt-4 text-lg font-semibold">Nenhum atendimento em andamento</p>
                    <p class="text-sm text-slate-500">{{ $queueCount ? $queueCount.' senha(s) aguardando na sua fila.' : 'Sua fila está vazia no momento.' }}</p>
                    <button class="btn btn-primary btn-lg mt-6 min-w-56" wire:click="callNext" wire:loading.attr="disabled" @disabled(! $queueCount)>
                        <x-icon name="megaphone" class="size-5" /> Chamar próxima
                    </button>
                </div>
            @endif
        </section>

        {{-- Fila --}}
        <section class="card flex max-h-[70vh] flex-col">
            <div class="card-header">
                <h2 class="card-title">Minha fila</h2>
                <span class="badge bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-100">{{ $queueCount }}</span>
            </div>

            @if ($this->myServices->isEmpty())
                <p class="p-5 text-sm text-slate-500">Você não tem serviços atribuídos nesta unidade. Peça ao gestor para configurar em <b>Configurações da unidade → Atendentes</b>.</p>
            @else
                <div class="flex flex-wrap gap-1.5 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    @foreach ($this->myServices as $su)
                        @php($n = $this->queue->where('service_id', $su->service_id)->count())
                        @if ($callByService && ! $current)
                            <button class="badge cursor-pointer bg-slate-100 text-slate-700 hover:bg-brand-100 dark:bg-slate-800 dark:text-slate-300" wire:click="callNext({{ $su->service_id }})" title="Chamar deste serviço" @disabled(! $n)>{{ $su->service->name }} · {{ $n }}</button>
                        @else
                            <span class="badge bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $su->service->name }} · {{ $n }}</span>
                        @endif
                    @endforeach
                </div>
                <ul class="flex-1 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                    @forelse ($this->queue as $t)
                        <li wire:key="q-{{ $t->id }}">
                            @php($slaState = $sla?->state($t, auth()->user()->currentUnit))
                            <button @class(['flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50',
                                'border-l-4 border-red-500 bg-red-50/60 dark:bg-red-950/30' => $slaState === 'breach',
                                'border-l-4 border-amber-400 bg-amber-50/60 dark:bg-amber-950/30' => $slaState === 'warning'])
                                    wire:click="openDetail({{ $t->id }})" @if ($slaState === 'breach') title="Acima da meta de espera" @endif>
                                <span class="w-16 font-mono text-lg font-bold" style="color: {{ $t->priority->color }}">{{ $t->code() }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">{{ $t->service->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $t->priority->name }}@if ($t->scheduled_at) · agendada {{ $t->localTime($t->scheduled_at, 'H:i') }}@endif</span>
                                </span>
                                <span class="text-xs text-slate-400 tabular-nums">{{ $t->localTime($t->arrived_at, 'H:i') }}</span>
                            </button>
                        </li>
                    @empty
                        <li class="p-8 text-center text-sm text-slate-500">Ninguém aguardando.</li>
                    @endforelse
                </ul>
            @endif
        </section>
    </div>

    {{-- Pausa --}}
    <x-modal wire:model="showPause" title="Iniciar pausa">
        <form wire:submit="startPause" class="space-y-4">
            <x-field :label="'Motivo'.($requireReason ? '' : ' (opcional)')">
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($pauseReasons as $r)
                        <label class="flex cursor-pointer items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm has-checked:border-brand-500 has-checked:bg-brand-50 dark:border-slate-700 dark:has-checked:bg-brand-900/30">
                            <span class="flex items-center gap-2"><input type="radio" wire:model="pauseReasonId" value="{{ $r->id }}" class="accent-brand-600"> {{ $r->name }}</span>
                            @if ($r->max_minutes)<span class="text-xs text-slate-400">até {{ $r->max_minutes }} min</span>@endif
                        </label>
                    @endforeach
                </div>
            </x-field>
            <x-field label="Observação (opcional)" error="pauseNotes"><input class="input" wire:model="pauseNotes" maxlength="255"></x-field>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Iniciar pausa</button>
            </div>
        </form>
    </x-modal>

    {{-- Local de atendimento --}}
    <x-modal wire:model="showSettings" title="Local de atendimento">
        <form wire:submit="saveSettings" class="space-y-4">
            <div class="grid grid-cols-[1fr_120px] gap-3">
                <x-field label="Local" error="locationId">
                    <select class="input" wire:model="locationId">
                        <option value="">Selecione…</option>
                        @foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Número" error="locationNumber">
                    <input type="number" min="1" class="input" wire:model="locationNumber">
                </x-field>
            </div>
            <x-field label="Tipo de atendimento" error="queueType" :hint="$canChangeType ? null : 'Alteração bloqueada pelo administrador.'">
                <select class="input" wire:model="queueType" @disabled(! $canChangeType)>
                    @foreach ($queueTypes as $qt)<option value="{{ $qt->value }}">{{ $qt->label() }}</option>@endforeach
                </select>
            </x-field>
            <div class="flex justify-end gap-2">
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </x-modal>

    {{-- Encerrar --}}
    <x-modal wire:model="showFinish" title="Encerrar atendimento" max-width="max-w-2xl">
        <form wire:submit="finish" class="space-y-4">
            <x-field label="Serviços realizados" error="performed">
                <div class="max-h-64 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    @foreach ($this->performableServices as $s)
                        <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" class="checkbox" value="{{ $s->id }}" wire:model="performed"> {{ $s->name }}</label>
                        @foreach ($s->children as $c)
                            <label class="ml-6 flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" value="{{ $c->id }}" wire:model="performed"> {{ $c->name }}</label>
                        @endforeach
                    @endforeach
                </div>
            </x-field>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field label="Resolução">
                    <select class="input" wire:model="resolution">
                        <option value="">Não informar</option>
                        @foreach ($resolutions as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach
                    </select>
                </x-field>
                <label class="flex items-center gap-2 self-end pb-2 text-sm"><input type="checkbox" class="checkbox" wire:model.live="redirectOnFinish"> Encaminhar para outro serviço</label>
            </div>
            @if ($redirectOnFinish)
                @include('livewire.partials.redirect-fields')
            @endif
            <x-field label="Observação" error="notes">
                <textarea class="input" rows="3" wire:model="notes"></textarea>
            </x-field>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary" wire:loading.attr="disabled">Encerrar</button>
            </div>
        </form>
    </x-modal>

    {{-- Redirecionar --}}
    <x-modal wire:model="showRedirect" title="Redirecionar senha">
        <form wire:submit="redirectTicket" class="space-y-4">
            <p class="text-sm text-slate-500">A senha atual será marcada como <b>erro de triagem</b> e uma nova senha com o mesmo número entrará na fila do serviço escolhido.</p>
            @include('livewire.partials.redirect-fields')
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary" wire:loading.attr="disabled">Redirecionar</button>
            </div>
        </form>
    </x-modal>

    {{-- Detalhe de senha da fila --}}
    <x-modal wire:model="showDetail" title="Senha na fila">
        @if ($d = $this->detail)
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Senha</dt><dd class="font-mono text-lg font-bold">{{ $d->code() }}</dd></div>
                <div><dt class="text-slate-500">Prioridade</dt><dd>{{ $d->priority->name }}</dd></div>
                <div><dt class="text-slate-500">Serviço</dt><dd>{{ $d->service->name }}</dd></div>
                <div><dt class="text-slate-500">Chegada</dt><dd>{{ $d->localTime($d->arrived_at) }}</dd></div>
                @if ($d->customer)<div class="col-span-2"><dt class="text-slate-500">Cliente</dt><dd>{{ $d->customer->name }} ({{ \App\Support\Privacy::document($d->customer->document) }})</dd></div>@endif
            </dl>
            @if ($callOutOfOrder && ! $current)
                <button class="btn btn-primary mt-5 w-full" wire:click="callTicket({{ $d->id }})"><x-icon name="megaphone" class="size-4" /> Chamar esta senha</button>
            @endif
        @endif
    </x-modal>
</div>
