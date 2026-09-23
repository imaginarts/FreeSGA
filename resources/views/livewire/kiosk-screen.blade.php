@php
    $kiosk = $this->kiosk;
    $contrast = $kiosk->setting('high_contrast');
    $color = $kiosk->setting('primary_color');
    $card = $contrast ? 'bg-black border-4 border-yellow-300 text-yellow-300' : 'bg-white text-slate-900 shadow-md';
@endphp
<div class="flex h-full flex-col {{ $contrast ? 'bg-black text-yellow-300' : '' }}" style="--kc: {{ $color }}"
     x-data="{
        idle: null,
        done: null,
        doc: '',
        touch() {
            clearTimeout(this.idle);
            if ($wire.step !== 'welcome') this.idle = setTimeout(() => $wire.resetKiosk(), {{ (int) $kiosk->setting('idle_seconds') }} * 1000);
        },
        key(d) { if (this.doc.length < 11) this.doc += d },
        masked() {
            const d = this.doc.padEnd(11, '_');
            if ($wire.step === 'phone') return '(' + d.slice(0,2) + ') ' + d.slice(2,7) + '-' + d.slice(7,11);
            return d.slice(0,3) + '.' + d.slice(3,6) + '.' + d.slice(6,9) + '-' + d.slice(9,11);
        },
     }"
     x-init="touch()"
     @pointerdown.window="touch()"
     @kiosk-print.window="$refs.frame.src = $event.detail.url + '&t=' + Date.now()">

    {{-- recriado a cada troca de etapa: reinicia os temporizadores --}}
    <span class="hidden" wire:key="step-{{ $step }}-{{ $ticketId }}"
          x-init="doc = ''; touch(); clearTimeout(done); @if ($step === 'done') done = setTimeout(() => $wire.resetKiosk(), {{ (int) $kiosk->setting('reset_seconds') }} * 1000) @endif"></span>

    <header class="flex items-center justify-between px-8 py-5 text-white" style="background: var(--kc)">
        <span class="text-2xl font-semibold">{{ $kiosk->unit->name }}</span>
        <span class="text-xl tabular-nums" x-data="{ t: '' }" x-init="const f = () => t = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: @js($kiosk->unit->timezone()) }); f(); setInterval(f, 10000)" x-text="t"></span>
    </header>

    <main class="flex flex-1 flex-col items-center justify-center overflow-y-auto p-8">
        @if (! $available)
            <p class="text-4xl font-semibold">Totem indisponível</p>
            <p class="mt-3 text-2xl opacity-70">Por favor, dirija-se à recepção.</p>

        @elseif ($step === 'welcome')
            <button wire:click="begin" class="kiosk-btn flex flex-col items-center gap-6 rounded-[2rem] px-16 py-14 text-center {{ $card }}">
                <span class="grid size-28 place-items-center rounded-3xl text-white" style="background: var(--kc)"><x-icon name="ticket" class="size-14" /></span>
                <span class="text-5xl font-bold">{{ $kiosk->setting('welcome_title') }}</span>
                <span class="text-3xl opacity-70">{{ $kiosk->setting('welcome_text') }}</span>
            </button>
            @if ($kiosk->setting('appointments'))
                <button wire:click="startAppointment" class="kiosk-btn mt-8 flex items-center gap-3 rounded-2xl px-10 py-6 text-2xl font-semibold {{ $card }}">
                    <x-icon name="calendar" class="size-8" /> Tenho agendamento
                </button>
            @endif

        @elseif ($step === 'services')
            <h1 class="mb-8 text-4xl font-bold">Escolha o serviço</h1>
            <div class="grid w-full max-w-5xl gap-5 {{ $this->unitServices->count() > 4 ? 'grid-cols-2 lg:grid-cols-3' : 'grid-cols-1 sm:grid-cols-2' }}">
                @forelse ($this->unitServices as $us)
                    @php($est = $this->estimates[$us->service_id] ?? null)
                    <button wire:click="chooseService({{ $us->service_id }})" wire:key="ks-{{ $us->id }}" class="kiosk-btn rounded-3xl px-6 py-8 text-left {{ $card }}">
                        <span class="block text-3xl font-bold">{{ $us->service->name }}</span>
                        @if ($us->service->description)<span class="mt-1 block text-lg opacity-70">{{ $us->service->description }}</span>@endif
                        @if ($est)
                            <span class="mt-3 flex items-center gap-2 text-lg font-medium opacity-80"><x-icon name="clock" class="size-5" />
                                {{ $est['eta'] === 0 ? 'Sem fila' : 'Espera '.\App\Services\WaitEstimator::format($est['eta']) }}</span>
                        @endif
                    </button>
                @empty
                    <p class="col-span-full text-center text-2xl opacity-70">Nenhum serviço disponível no momento.</p>
                @endforelse
            </div>

        @elseif ($step === 'type')
            <h1 class="mb-8 text-4xl font-bold">Tipo de atendimento</h1>
            <div class="grid w-full max-w-4xl gap-6 sm:grid-cols-2">
                <button wire:click="chooseNormal" class="kiosk-btn rounded-3xl px-8 py-14 text-center {{ $card }}">
                    <span class="block text-4xl font-bold">Normal</span>
                </button>
                <button wire:click="choosePreferential" class="kiosk-btn rounded-3xl px-8 py-14 text-center {{ $contrast ? $card : 'bg-red-600 text-white shadow-md' }}">
                    <span class="block text-4xl font-bold">Preferencial</span>
                    <span class="mt-2 block text-xl opacity-90">Idosos, gestantes, pessoas com deficiência, lactantes e com criança de colo</span>
                </button>
            </div>

        @elseif ($step === 'priority')
            <h1 class="mb-8 text-4xl font-bold">Selecione a prioridade</h1>
            <div class="grid w-full max-w-4xl gap-5 sm:grid-cols-2">
                @foreach ($this->priorities as $p)
                    <button wire:click="choosePriority({{ $p->id }})" wire:key="kp-{{ $p->id }}" class="kiosk-btn flex items-center gap-4 rounded-3xl px-8 py-8 text-left {{ $card }}">
                        <span class="size-6 shrink-0 rounded-full" style="background: {{ $p->color }}"></span>
                        <span><span class="block text-3xl font-bold">{{ $p->name }}</span><span class="block text-lg opacity-70">{{ $p->description }}</span></span>
                    </button>
                @endforeach
            </div>

        @elseif ($step === 'document' || $step === 'phone' || ($step === 'appointment' && $this->appointments->isEmpty()))
            <h1 class="mb-2 text-center text-4xl font-bold">{{ match ($step) { 'appointment' => 'Digite seu CPF para localizar o agendamento', 'phone' => 'Quer receber avisos no WhatsApp?', default => 'Digite seu CPF' } }}</h1>
            @if ($step === 'phone')
                <p class="text-xl opacity-70">Avisamos quando sua vez estiver chegando. Digite DDD + número ou toque em Pular.</p>
                <p class="mb-4 max-w-2xl text-center text-base opacity-60">{{ \App\Models\Setting::get('privacy')['phone_consent_text'] }}</p>
            @endif
            @if ($step === 'document' && ! $kiosk->setting('require_document'))<p class="mb-4 text-xl opacity-70">Opcional</p>@endif
            <p class="my-6 font-mono text-6xl tracking-wider" x-text="masked()"></p>
            <div class="grid w-full max-w-md grid-cols-3 gap-4">
                @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n)
                    <button type="button" @click="key('{{ $n }}')" class="kiosk-btn rounded-2xl py-6 text-4xl font-bold {{ $card }}">{{ $n }}</button>
                @endforeach
                <button type="button" @click="doc = ''" class="kiosk-btn rounded-2xl py-6 text-2xl font-semibold {{ $card }}">Limpar</button>
                <button type="button" @click="key('0')" class="kiosk-btn rounded-2xl py-6 text-4xl font-bold {{ $card }}">0</button>
                <button type="button" @click="doc = doc.slice(0, -1)" class="kiosk-btn rounded-2xl py-6 text-2xl font-semibold {{ $card }}">⌫</button>
            </div>
            <div class="mt-6 flex w-full max-w-md gap-4">
                @if ($step === 'document' && ! $kiosk->setting('require_document'))
                    <button wire:click="submitDocument('')" class="kiosk-btn flex-1 rounded-2xl py-5 text-2xl font-semibold {{ $card }}">Pular</button>
                @elseif ($step === 'phone')
                    <button wire:click="submitPhone('')" class="kiosk-btn flex-1 rounded-2xl py-5 text-2xl font-semibold {{ $card }}">Pular</button>
                @endif
                <button @click="{{ match ($step) { 'appointment' => '$wire.findAppointments(doc)', 'phone' => '$wire.submitPhone(doc)', default => '$wire.submitDocument(doc)' } }}"
                        :disabled="{{ $step === 'phone' ? 'doc.length < 10' : 'doc.length !== 11' }}"
                        class="kiosk-btn flex-1 rounded-2xl py-5 text-2xl font-bold text-white disabled:opacity-40" style="background: var(--kc)">Confirmar</button>
            </div>

        @elseif ($step === 'appointment')
            <h1 class="mb-8 text-4xl font-bold">Seus agendamentos de hoje</h1>
            <div class="grid w-full max-w-3xl gap-5">
                @foreach ($this->appointments as $a)
                    <button wire:click="confirmAppointment({{ $a->id }})" wire:key="ka-{{ $a->id }}" class="kiosk-btn flex items-center justify-between rounded-3xl px-8 py-8 text-left {{ $card }}">
                        <span><span class="block text-3xl font-bold">{{ $a->service->name }}</span><span class="block text-xl opacity-70">Horário: {{ substr($a->time, 0, 5) }}</span></span>
                        <span class="rounded-2xl px-6 py-3 text-2xl font-bold text-white" style="background: var(--kc)">Confirmar chegada</span>
                    </button>
                @endforeach
            </div>

        @elseif ($step === 'done' && ($t = $this->ticket))
            <p class="text-3xl font-medium opacity-70">Sua senha é</p>
            <p class="my-2 font-mono text-[10rem] leading-none font-bold" style="color: {{ $contrast ? '#fde047' : $t->priority->color }}">{{ $t->code() }}</p>
            <p class="text-3xl font-semibold">{{ $t->service->name }}</p>
            @if ($t->priority->weight > 0)<p class="text-2xl opacity-80">{{ $t->priority->name }}</p>@endif
            @if ($kiosk->print_mode !== 'none' && ! $notice)<p class="mt-6 text-2xl">Retire sua senha impressa abaixo</p>@endif
            @if ($notice)<p class="mt-6 rounded-2xl bg-amber-100 px-6 py-3 text-2xl text-amber-900">{{ $notice }}</p>@endif
            @if ($showQr)
                <div class="mt-8 flex items-center gap-6 rounded-3xl bg-white p-5 text-slate-900 shadow-md">
                    <div class="w-44 [&_svg]:h-auto [&_svg]:w-full">{!! $t->trackingQrSvg(180) !!}</div>
                    <p class="max-w-64 text-xl">Aponte a câmera do celular para acompanhar sua vez sem ficar na fila</p>
                </div>
            @endif
            <button wire:click="resetKiosk" class="kiosk-btn mt-8 rounded-2xl px-10 py-4 text-2xl font-semibold {{ $card }}">Concluir</button>
        @endif

        @if ($error)
            <p class="mt-8 max-w-3xl rounded-2xl bg-red-100 px-6 py-4 text-center text-2xl text-red-800">{{ $error }}</p>
        @endif
    </main>

    @if ($available && ! in_array($step, ['welcome', 'done']))
        <footer class="flex justify-between gap-4 px-8 pb-8">
            <button wire:click="back" class="kiosk-btn rounded-2xl px-10 py-5 text-2xl font-semibold {{ $card }}">← Voltar</button>
            <button wire:click="resetKiosk" class="kiosk-btn rounded-2xl px-10 py-5 text-2xl font-semibold {{ $card }}">Início</button>
        </footer>
    @endif

    <iframe x-ref="frame" class="hidden" title="Impressão"></iframe>
</div>
