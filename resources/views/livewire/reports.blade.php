<div>
    <x-page-header title="Relatórios" subtitle="Estatísticas da unidade, incluindo senhas já arquivadas">
        <input type="date" class="input w-40" wire:model.live="start">
        <span class="text-slate-400">até</span>
        <input type="date" class="input w-40" wire:model.live="end">
        <select class="input w-48" wire:model.live="userId">
            <option value="">Todos os atendentes</option>
            @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->fullName() }}</option>@endforeach
        </select>
    </x-page-header>
    @error('end')<p class="error -mt-4 mb-4">{{ $message }}</p>@enderror

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="card card-body"><p class="text-sm text-slate-500">Senhas no período</p><p class="mt-1 font-mono text-3xl font-semibold">{{ $total }}</p></div>
        <div class="card card-body"><p class="text-sm text-slate-500">Encerradas</p><p class="mt-1 font-mono text-3xl font-semibold text-emerald-600">{{ $charts['status']['Encerrada'] ?? 0 }}</p></div>
        <div class="card card-body"><p class="text-sm text-slate-500">Espera média</p><p class="mt-1 font-mono text-3xl font-semibold">{{ \App\Models\Ticket::formatSeconds($charts['averages']['Espera']) }}</p></div>
        <div class="card card-body"><p class="text-sm text-slate-500">Atendimento médio</p><p class="mt-1 font-mono text-3xl font-semibold">{{ \App\Models\Ticket::formatSeconds($charts['averages']['Atendimento']) }}</p></div>
    </div>

    <div wire:ignore class="mb-8 grid gap-6 lg:grid-cols-3"
         x-data="{
            charts: {},
            palette: ['#0284c7', '#f59e0b', '#2563eb', '#059669', '#ea580c', '#dc2626', '#9333ea', '#0891b2', '#65a30d', '#db2777'],
            fmt(s) { s = Math.round(s); return [Math.floor(s / 3600), Math.floor(s % 3600 / 60), s % 60].map(n => String(n).padStart(2, '0')).join(':') },
            draw(data) {
                if (! window.Chart) return;
                const dark = document.documentElement.classList.contains('dark');
                Chart.defaults.color = dark ? '#94a3b8' : '#64748b';
                Chart.defaults.borderColor = dark ? '#1e293b' : '#e2e8f0';
                const sets = {
                    status: { type: 'doughnut', data: data.status, opts: { plugins: { legend: { position: 'right' } } } },
                    services: { type: 'bar', data: data.services, opts: { indexAxis: 'y', plugins: { legend: { display: false } } } },
                    averages: { type: 'bar', data: data.averages, opts: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => this.fmt(c.raw) } } }, scales: { y: { ticks: { callback: v => this.fmt(v) } } } } },
                };
                for (const [key, cfg] of Object.entries(sets)) {
                    this.charts[key]?.destroy();
                    const labels = Object.keys(cfg.data), values = Object.values(cfg.data);
                    this.charts[key] = new Chart(this.$refs[key], {
                        type: cfg.type,
                        data: { labels, datasets: [{ data: values, backgroundColor: key === 'status' ? this.palette : '#0284c7', borderRadius: cfg.type === 'bar' ? 6 : 0 }] },
                        options: { responsive: true, maintainAspectRatio: false, ...cfg.opts },
                    });
                }
            },
         }"
         x-init="$nextTick(() => draw(@js($charts)))"
         @charts-updated.window="draw($event.detail.charts)">
        <div class="card"><div class="card-header"><h2 class="card-title">Senhas por status</h2></div><div class="card-body h-72"><canvas x-ref="status"></canvas></div></div>
        <div class="card"><div class="card-header"><h2 class="card-title">Encerradas por serviço</h2></div><div class="card-body h-72"><canvas x-ref="services"></canvas></div></div>
        <div class="card"><div class="card-header"><h2 class="card-title">Tempos médios</h2></div><div class="card-body h-72"><canvas x-ref="averages"></canvas></div></div>
    </div>

    <h2 class="mb-3 text-sm font-semibold text-slate-500 uppercase">Relatórios para impressão</h2>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($reports as $key => [$label, $usesPeriod])
            <a target="_blank" href="{{ route('reports.show', ['report' => $key, 'start' => $start, 'end' => $end, 'user' => $userId]) }}"
               class="card flex items-center justify-between p-4 hover:border-brand-500">
                <span>
                    <span class="block font-medium">{{ $label }}</span>
                    <span class="block text-xs text-slate-500">{{ $usesPeriod ? 'Usa o período selecionado' : 'Situação atual' }}</span>
                </span>
                <x-icon name="printer" class="size-5 text-slate-400" />
            </a>
        @endforeach
    </div>
</div>
