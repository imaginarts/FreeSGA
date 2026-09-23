@php use App\Enums\ServiceType; @endphp
<div wire:poll.30s
     x-data="{
        autoPrint: $persist(true).as('sga.triage.print'),
        printerId: $persist('').as('sga.triage.printer'),
        printers: @js($this->printers->pluck('id')->map(fn ($id) => (string) $id)),
        print(id, url) {
            // impressora térmica escolhida nesta estação: o servidor imprime direto
            if (this.printerId && this.printers.includes(String(this.printerId))) {
                $wire.printDirect(id, Number(this.printerId));
                return;
            }
            this.$refs.frame.src = url + (url.includes('?') ? '&' : '?') + 'autoprint=1&t=' + Date.now();
        },
     }"
     @ticket-issued.window="if (autoPrint) print($event.detail.id, $event.detail.url)"
     @print-ticket.window="print($event.detail.id, $event.detail.url)">

    <x-page-header title="Triagem" subtitle="Emita senhas para os serviços da unidade">
        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" class="checkbox" x-model="autoPrint"> Imprimir automaticamente
        </label>
        @if ($this->printers->isNotEmpty())
            <select class="input !w-auto !py-1.5" x-model="printerId" title="Impressora desta estação">
                <option value="">Impressora do navegador</option>
                @foreach ($this->printers as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
        @endif
        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            <input type="checkbox" class="checkbox" wire:click="toggleAskCustomer" @checked($askCustomer)> Identificar cliente
        </label>
        @if (auth()->user()->canAccessModule(\App\Enums\Module::Scheduling))
            <button class="btn btn-secondary" wire:click="$set('showAppointments', true)"><x-icon name="calendar" class="size-4" /> Agendados hoje</button>
        @endif
        <button class="btn btn-secondary" wire:click="$set('showSearch', true)"><x-icon name="search" class="size-4" /> Consultar senha</button>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="grid content-start gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($this->unitServices as $us)
                @php($count = $this->counts[$us->service_id] ?? null)
                <div class="card flex flex-col" wire:key="us-{{ $us->id }}">
                    <div class="flex items-start justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $us->service->name }}</p>
                            <p class="line-clamp-2 text-xs text-slate-500">{{ $us->service->description ?: $us->service->children->pluck('name')->join(', ') }}</p>
                        </div>
                        <span class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-sm font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $us->prefix }}</span>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 px-4 pb-3 text-xs text-slate-500">
                        <span>Aguardando: <b class="text-slate-800 dark:text-slate-200">{{ (int) ($count->waiting ?? 0) }}</b></span>
                        <span>Emitidas: <b class="text-slate-800 dark:text-slate-200">{{ (int) ($count->total ?? 0) }}</b></span>
                        @isset($this->estimates[$us->service_id])
                            <span class="flex items-center gap-1"><x-icon name="clock" class="size-3.5" /> Espera: <b class="text-slate-800 dark:text-slate-200">{{ $this->estimates[$us->service_id]['eta'] === 0 ? 'imediata' : \App\Services\WaitEstimator::format($this->estimates[$us->service_id]['eta']) }}</b></span>
                        @endisset
                    </div>
                    <div class="mt-auto grid grid-cols-2 gap-2 border-t border-slate-100 p-3 dark:border-slate-800">
                        @if ($hasNormal && $us->type !== ServiceType::PriorityOnly)
                            <button class="btn btn-primary btn-lg" wire:click="choose({{ $us->service_id }}, false)" wire:loading.attr="disabled" @class(['col-span-2' => ! $hasPriority || $us->type === ServiceType::NormalOnly])>Normal</button>
                        @endif
                        @if ($hasPriority && $us->type !== ServiceType::NormalOnly)
                            <button class="btn btn-danger btn-lg" wire:click="choose({{ $us->service_id }}, true)" wire:loading.attr="disabled" @class(['col-span-2' => ! $hasNormal || $us->type === ServiceType::PriorityOnly])>Prioridade</button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="card card-body col-span-full text-center text-slate-500">
                    Nenhum serviço ativo nesta unidade. Habilite serviços em <b>Configurações da unidade</b>.
                </div>
            @endforelse
        </div>

        <aside class="space-y-4">
            <div class="card overflow-hidden">
                <div class="bg-brand-600 px-5 py-3 text-sm font-medium text-white">Última senha emitida</div>
                @if ($this->lastTicket)
                    <div class="p-5 text-center">
                        <p class="font-mono text-6xl font-bold tracking-wider" style="color: {{ $this->lastTicket->priority->color }}">{{ $this->lastTicket->code() }}</p>
                        <p class="mt-2 font-medium">{{ $this->lastTicket->service->name }}</p>
                        <p class="text-sm text-slate-500">{{ $this->lastTicket->priority->name }} · {{ $this->lastTicket->localTime($this->lastTicket->arrived_at, 'H:i') }}</p>
                        @if (auth()->user()->currentUnit->feature('triage_show_qr'))
                            <div class="mx-auto mt-4 w-40 rounded-xl bg-white p-2 [&_svg]:h-auto [&_svg]:w-full">{!! $this->lastTicket->trackingQrSvg(150) !!}</div>
                            <p class="mt-1 text-xs text-slate-500">Aponte a câmera para acompanhar pelo celular</p>
                        @endif
                        <button class="btn btn-secondary mt-4 w-full" wire:click="reprint({{ $this->lastTicket->id }})"><x-icon name="printer" class="size-4" /> Reimprimir</button>
                    </div>
                @else
                    <p class="p-8 text-center text-sm text-slate-500">Nenhuma senha emitida nesta sessão.</p>
                @endif
            </div>
            <div class="card card-body text-sm text-slate-500">
                <p class="flex items-center gap-2 font-medium text-slate-700 dark:text-slate-200"><x-icon name="info" class="size-4" /> Dica</p>
                <p class="mt-1">Ative "Identificar cliente" para registrar documento e nome do cliente junto com a senha.</p>
            </div>
        </aside>
    </div>

    <iframe x-ref="frame" class="hidden" title="Impressão"></iframe>

    {{-- Emissão com escolha de prioridade / cliente --}}
    <x-modal wire:model="showIssue" title="Emitir senha">
        <form wire:submit="issue" class="space-y-4">
            <x-field label="Prioridade">
                <div class="grid gap-2">
                    @foreach ($this->priorities->filter(fn ($p) => $issuePriority ? $p->weight > 0 : $p->weight === 0) as $p)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 has-checked:border-brand-500 has-checked:bg-brand-50 dark:border-slate-700 dark:has-checked:bg-brand-900/30">
                            <input type="radio" wire:model="issuePriorityId" value="{{ $p->id }}" class="accent-brand-600">
                            <span class="size-3 rounded-full" style="background: {{ $p->color }}"></span>
                            <span class="text-sm"><b>{{ $p->name }}</b> <span class="text-slate-500">{{ $p->description }}</span></span>
                        </label>
                    @endforeach
                </div>
            </x-field>
            @if ($askCustomer)
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Documento (CPF)">
                        <input class="input" wire:model.live.debounce.400ms="customerDocument" placeholder="Opcional">
                    </x-field>
                    <x-field label="Nome do cliente">
                        <input class="input" wire:model="customerName" placeholder="Opcional">
                    </x-field>
                    @if (auth()->user()->currentUnit->feature('notify_enabled'))
                        <x-field label="WhatsApp para avisos" error="customerPhone" :hint="'Informe ao cliente: '.\App\Models\Setting::get('privacy')['phone_consent_text']" class="sm:col-span-2">
                            <input class="input" type="tel" wire:model="customerPhone" placeholder="(11) 98765-4321 — opcional">
                        </x-field>
                    @endif
                </div>
            @endif
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary" wire:loading.attr="disabled"><x-icon name="ticket" class="size-4" /> Emitir senha</button>
            </div>
        </form>
    </x-modal>

    {{-- Consulta --}}
    <x-modal wire:model="showSearch" title="Consultar senha" max-width="max-w-3xl">
        <input class="input mb-4" wire:model.live.debounce.300ms="search" placeholder="Ex.: A12, B, 45">
        <div class="max-h-96 overflow-y-auto">
            <table class="table">
                <thead><tr><th>Senha</th><th>Serviço</th><th>Chegada</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($this->searchResults as $t)
                    <tr wire:key="s-{{ $t->id }}">
                        <td class="font-mono font-semibold">{{ $t->code() }}</td>
                        <td>{{ $t->service->name }}</td>
                        <td>{{ $t->localTime($t->arrived_at, 'd/m H:i') }}</td>
                        <td><x-status-badge :status="$t->status" /></td>
                        <td class="text-right"><button class="btn btn-ghost btn-sm" wire:click="reprint({{ $t->id }})"><x-icon name="printer" class="size-4" /></button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-500">{{ $search ? 'Nenhuma senha encontrada.' : 'Digite a senha para pesquisar.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-modal>

    {{-- Agendamentos do dia --}}
    <x-modal wire:model="showAppointments" title="Agendamentos de hoje" max-width="max-w-3xl">
        <table class="table">
            <thead><tr><th>Hora</th><th>Cliente</th><th>Serviço</th><th>Situação</th><th></th></tr></thead>
            <tbody>
            @forelse ($this->appointments as $a)
                <tr wire:key="a-{{ $a->id }}">
                    <td class="font-mono">{{ substr($a->time, 0, 5) }}</td>
                    <td>{{ $a->customer->name }}<br><span class="text-xs text-slate-500">{{ \App\Support\Privacy::document($a->customer->document) }}</span></td>
                    <td>{{ $a->service->name }}</td>
                    <td>{{ $a->status->label() }}</td>
                    <td class="text-right">
                        @if ($a->status === $appointmentStatus::Scheduled)
                            <button class="btn btn-primary btn-sm" wire:click="confirmAppointment({{ $a->id }})">Confirmar chegada</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-slate-500">Nenhum agendamento para hoje.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-modal>
</div>
