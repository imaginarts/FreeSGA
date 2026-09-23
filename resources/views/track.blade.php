@php($appearance = \App\Models\Setting::get('appearance'))
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $appearance['primary_color'] }}">
    <meta name="robots" content="noindex">
    <title>Senha {{ $ticket->code() }} · {{ $unit->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/track.js'])
    <style>
        :root { --color-brand-600: {{ $appearance['primary_color'] }}; }
        @keyframes pulse-ring { 0% { box-shadow: 0 0 0 0 rgb(16 185 129 / .6) } 100% { box-shadow: 0 0 0 28px rgb(16 185 129 / 0) } }
        .called { animation: pulse-ring 1.4s ease-out infinite; }
    </style>
</head>
<body class="min-h-full bg-slate-100 font-sans text-slate-900 antialiased">
<main id="track" class="mx-auto flex min-h-full max-w-md flex-col gap-4 p-4"
      data-url="{{ route('ticket.track.data', [$ticket, $token]) }}"
      data-channel="unit.{{ $unit->id }}">

    <header class="pt-2 text-center">
        <p class="text-sm font-medium text-slate-500">{{ $unit->name }}</p>
        <p class="text-xs text-slate-400">Acompanhe sua senha em tempo real</p>
    </header>

    <section id="card" class="rounded-3xl bg-white p-6 text-center shadow-sm transition-colors">
        <p class="text-xs font-semibold tracking-[0.3em] text-slate-400 uppercase">Sua senha</p>
        <p id="code" class="mt-1 font-mono text-7xl font-bold tracking-wider text-brand-600">{{ $ticket->code() }}</p>
        <p id="service" class="mt-1 font-medium text-slate-600">{{ $ticket->service->name }}</p>
        <div id="status" class="mt-5 rounded-2xl bg-slate-50 px-4 py-4 text-lg font-semibold text-slate-700">Carregando…</div>
    </section>

    <a id="survey" href="#" class="hidden rounded-3xl bg-brand-600 p-5 text-center text-lg font-semibold text-white shadow-sm">
        ⭐ Avalie seu atendimento
        <span class="block text-sm font-normal text-white/80">Leva menos de 10 segundos</span>
    </a>

    <section id="stats" class="grid grid-cols-2 gap-4">
        <div id="position-box" class="hidden rounded-3xl bg-white p-5 text-center shadow-sm">
            <p class="text-xs font-semibold text-slate-400 uppercase">Posição</p>
            <p id="position" class="mt-1 font-mono text-4xl font-bold">–</p>
            <p class="text-xs text-slate-400">na fila</p>
        </div>
        <div id="eta-box" class="hidden rounded-3xl bg-white p-5 text-center shadow-sm">
            <p class="text-xs font-semibold text-slate-400 uppercase">Estimativa</p>
            <p id="eta" class="mt-2 text-2xl font-bold">–</p>
            <p class="text-xs text-slate-400">de espera</p>
        </div>
    </section>

    <section id="calls-box" class="hidden rounded-3xl bg-white p-5 shadow-sm">
        <p class="mb-2 text-xs font-semibold text-slate-400 uppercase">Últimas chamadas deste serviço</p>
        <ul id="calls" class="divide-y divide-slate-100 text-sm"></ul>
    </section>

    <button id="notify" class="hidden rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700">
        Ativar aviso sonoro e notificação
    </button>

    <footer class="mt-auto pb-2 text-center text-xs text-slate-400">
        Mantenha esta página aberta. Ela atualiza sozinha.
    </footer>
</main>
</body>
</html>
