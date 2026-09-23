@php
    $groups = [
        'Sistema' => [
            ['admin.index', 'Geral e dados', 'cog'],
            ['admin.privacy', 'Privacidade e auditoria', 'eye'],
        ],
        'Cadastros' => [
            ['admin.units', 'Unidades', 'building'],
            ['admin.services', 'Serviços', 'layers'],
            ['admin.priorities', 'Prioridades', 'flag'],
            ['admin.locations', 'Locais', 'map-pin'],
            ['admin.departments', 'Departamentos', 'folder'],
            ['admin.roles', 'Perfis', 'shield'],
            ['admin.pause-reasons', 'Motivos de pausa', 'clock'],
        ],
        'Integrações' => [
            ['admin.messaging', 'WhatsApp / SMS', 'bell'],
            ['admin.api', 'API (tokens)', 'key'],
            ['admin.webhooks', 'Webhooks', 'webhook'],
        ],
    ];
@endphp
<div class="grid gap-6 lg:grid-cols-[220px_1fr]">
    <nav class="space-y-5">
        @foreach ($groups as $group => $items)
            <div>
                <p class="mb-1.5 px-3 text-xs font-semibold tracking-wide text-slate-400 uppercase">{{ $group }}</p>
                @foreach ($items as [$route, $label, $icon])
                    <a href="{{ route($route) }}" wire:navigate
                       class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs($route) ? 'bg-white text-brand-700 shadow-sm dark:bg-slate-900 dark:text-brand-100' : 'text-slate-600 hover:bg-white/60 dark:text-slate-400 dark:hover:bg-slate-900/60' }}">
                        <x-icon :name="$icon" class="size-4" /> {{ $label }}
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>
    <div class="min-w-0">{{ $slot }}</div>
</div>
