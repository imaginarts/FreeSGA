<x-layouts::app title="Início">
    <x-page-header :title="'Olá, '.auth()->user()->name" :subtitle="$unit ? 'Você está na unidade '.$unit->name : 'Você ainda não está lotado em nenhuma unidade.'" />

    @if ($stats)
        <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ([
                ['Senhas emitidas hoje', $stats['issued'], 'ticket', 'text-brand-600'],
                ['Aguardando agora', $stats['waiting'], 'clock', 'text-amber-600'],
                ['Atendimentos encerrados', $stats['finished'], 'check', 'text-emerald-600'],
                ['Espera média', \App\Models\Ticket::formatSeconds($stats['avg_wait']), 'headset', 'text-purple-600'],
            ] as [$label, $value, $icon, $color])
                <div class="card card-body">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-slate-500">{{ $label }}</p>
                        <x-icon :name="$icon" class="size-5 {{ $color }}" />
                    </div>
                    <p class="mt-2 font-mono text-3xl font-semibold tabular-nums">{{ $value }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if ($modules->isNotEmpty())
        <h2 class="mb-3 text-sm font-semibold text-slate-500 uppercase">Módulos</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($modules as $module)
                <a href="{{ route($module->route()) }}" wire:navigate class="card group flex items-center gap-4 p-5 transition hover:border-brand-500 hover:shadow-md">
                    <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 transition group-hover:bg-brand-600 group-hover:text-white dark:bg-brand-900/40">
                        <x-icon :name="$module->icon()" class="size-6" />
                    </span>
                    <span>
                        <span class="block font-semibold">{{ $module->label() }}</span>
                        <span class="block text-sm text-slate-500">{{ $module->description() }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @elseif (! auth()->user()->is_admin)
        <div class="card card-body text-center text-slate-500">Peça a um administrador para lotar você em uma unidade com um perfil de acesso.</div>
    @endif
</x-layouts::app>
