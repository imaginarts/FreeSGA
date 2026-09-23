<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $panel->name }} · {{ $unit->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/panel.js'])
    <style>
        :root {
            --bg-highlight: {{ $panel->setting('highlight_bg') }};
            --bg-history: {{ $panel->setting('history_bg') }};
            --bg-footer: {{ $panel->setting('footer_bg') }};
        }
        @keyframes flash { 0%, 100% { filter: none } 50% { filter: brightness(1.8) } }
        .flash { animation: flash .6s ease-in-out 3; }
    </style>
</head>
<body class="h-full overflow-hidden bg-black font-sans text-white antialiased">
<div id="panel" class="grid h-full grid-cols-1 grid-rows-[1fr_auto] lg:grid-cols-[1fr_32%]"
     data-url="{{ route('panel.data', $panel) }}"
     data-channel="unit.{{ $unit->id }}.panel"
     data-services="{{ $panel->services()->pluck('services.id')->join(',') }}"
     data-voice="{{ $panel->setting('voice') ? 1 : 0 }}"
     data-sound="{{ $panel->setting('sound') ? 1 : 0 }}"
     data-timezone="{{ $unit->timezone() }}">

    {{-- Chamada em destaque --}}
    <section class="flex flex-col items-center justify-center p-8 text-center" style="background: var(--bg-highlight)">
        <p class="text-2xl font-medium tracking-[0.3em] text-white/70 uppercase lg:text-3xl">Senha</p>
        <p id="current-code" class="font-mono text-[22vw] leading-none font-bold tracking-wider lg:text-[15vw]">---</p>
        <p id="current-location" class="mt-4 text-5xl font-semibold lg:text-7xl">Aguardando chamada…</p>
        <p id="current-extra" class="mt-4 text-2xl text-white/80 lg:text-3xl"></p>
    </section>

    {{-- Histórico e relógio --}}
    <aside class="flex min-h-0 flex-col" style="background: var(--bg-history)">
        <div class="border-b border-white/10 p-6 text-center">
            <p id="clock" class="font-mono text-6xl font-bold tabular-nums">--:--</p>
            <p id="date" class="mt-1 text-lg text-white/70 capitalize"></p>
        </div>
        @if ($panel->setting('show_eta'))
            <div class="border-b border-white/10 px-6 py-4">
                <p class="pb-2 text-sm font-semibold tracking-widest text-white/50 uppercase">Tempo estimado de espera</p>
                <ul id="estimates" class="space-y-1 text-lg"></ul>
            </div>
        @endif
        <p class="px-6 pt-5 pb-2 text-sm font-semibold tracking-widest text-white/50 uppercase">Últimas chamadas</p>
        <ul id="history" class="flex-1 space-y-2 overflow-hidden px-4 pb-4"></ul>
    </aside>

    <footer class="col-span-full flex items-center justify-between px-6 py-3 text-lg" style="background: var(--bg-footer)">
        <span class="font-semibold">{{ $unit->name }}</span>
        <span class="text-white/80">{{ $panel->setting('footer_text') }}</span>
    </footer>

    {{-- Navegadores exigem um clique antes de liberar áudio --}}
    <button id="unlock" class="fixed inset-0 z-10 flex flex-col items-center justify-center gap-4 bg-black/80 text-2xl">
        <span class="rounded-2xl bg-white px-8 py-4 font-semibold text-slate-900">Clique para iniciar o painel</span>
        <span class="text-base text-white/60">Necessário para liberar som e voz · pressione F11 para tela cheia</span>
    </button>
</div>
</body>
</html>
