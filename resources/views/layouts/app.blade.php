@php
    use App\Enums\Module;
    $user = auth()->user();
    $appearance = \App\Models\Setting::get('appearance');
    $modules = $user ? collect(Module::cases())->filter(fn ($m) => $currentUnit && $user->canAccessModule($m)) : collect();
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="h-full" x-data="{ dark: localStorage.getItem('sga.dark') === '1' }" x-init="$watch('dark', v => localStorage.setItem('sga.dark', v ? '1' : '0'))" :class="{ 'dark': dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ $appearance['app_name'] }}</title>
    <script>if (localStorage.getItem('sga.dark') === '1') document.documentElement.classList.add('dark')</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>:root { --color-brand-600: {{ $appearance['primary_color'] }}; }</style>
    @livewireStyles
</head>
<body class="h-full bg-slate-100 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<div class="flex min-h-full flex-col">
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95" x-data="{ mobile: false }">
        <div class="mx-auto flex h-14 max-w-7xl items-center gap-3 px-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold text-slate-900 dark:text-white" wire:navigate>
                <span class="grid size-8 place-items-center rounded-lg bg-brand-600 text-white"><x-icon name="ticket" class="size-4.5" /></span>
                <span class="hidden sm:inline">{{ $appearance['app_name'] }}</span>
            </a>

            @if ($user)
                {{-- Unidade atual --}}
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button @click="open = !open" class="flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                        <x-icon name="building" class="size-4 text-slate-500" />
                        <span class="max-w-40 truncate font-medium">{{ $currentUnit?->name ?? 'Sem unidade' }}</span>
                        <x-icon name="chevron-down" class="size-4 text-slate-400" />
                    </button>
                    <div x-cloak x-show="open" x-transition class="absolute left-0 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                        <p class="px-2.5 py-1.5 text-xs font-semibold text-slate-400 uppercase">Trocar unidade</p>
                        @forelse ($availableUnits ?? [] as $unit)
                            <form method="POST" action="{{ route('unit.switch', $unit) }}">
                                @csrf
                                <button class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800">
                                    {{ $unit->name }}
                                    @if ($unit->id === $currentUnit?->id)<x-icon name="check" class="size-4 text-brand-600" />@endif
                                </button>
                            </form>
                        @empty
                            <p class="px-2.5 py-2 text-sm text-slate-500">Nenhuma unidade disponível.</p>
                        @endforelse
                    </div>
                </div>

                <nav class="ml-2 hidden items-center gap-0.5 lg:flex">
                    @foreach ($modules as $module)
                        <a href="{{ route($module->route()) }}" wire:navigate
                           class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium {{ request()->routeIs($module->route().'*') ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-100' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                            <x-icon :name="$module->icon()" class="size-4" /> {{ $module->label() }}
                        </a>
                    @endforeach
                </nav>

                <div class="ml-auto flex items-center gap-1">
                    <button @click="dark = !dark" class="btn btn-ghost !p-2" title="Alternar tema">
                        <x-icon name="moon" class="size-4.5" x-show="!dark" />
                        <x-icon name="sun" class="size-4.5" x-show="dark" x-cloak />
                    </button>
                    @if ($user->is_admin)
                        <a href="{{ route('admin.index') }}" wire:navigate class="btn btn-ghost !p-2 {{ request()->routeIs('admin.*') ? 'text-brand-600' : '' }}" title="Administração"><x-icon name="shield" class="size-4.5" /></a>
                    @endif
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button @click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">
                            <span class="grid size-7 place-items-center rounded-full bg-slate-200 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                            <span class="hidden text-sm font-medium md:inline">{{ $user->name }}</span>
                        </button>
                        <div x-cloak x-show="open" x-transition class="absolute right-0 mt-2 w-52 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                            <div class="border-b border-slate-100 px-2.5 py-2 dark:border-slate-800">
                                <p class="text-sm font-medium">{{ $user->fullName() }}</p>
                                <p class="text-xs text-slate-500">{{ '@'.$user->login }}</p>
                            </div>
                            <a href="{{ route('profile') }}" wire:navigate class="mt-1 flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800"><x-icon name="user" class="size-4" /> Meu perfil</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40"><x-icon name="logout" class="size-4" /> Sair</button>
                            </form>
                        </div>
                    </div>
                    <button class="btn btn-ghost !p-2 lg:hidden" @click="mobile = !mobile"><x-icon name="menu" /></button>
                </div>
            @endif
        </div>

        @if ($user)
            <nav x-cloak x-show="mobile" class="border-t border-slate-200 px-4 py-2 lg:hidden dark:border-slate-800">
                @foreach ($modules as $module)
                    <a href="{{ route($module->route()) }}" class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800"><x-icon :name="$module->icon()" class="size-4" /> {{ $module->label() }}</a>
                @endforeach
            </nav>
        @endif
    </header>

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6">
        @if (session('error'))
            <div class="mb-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300"><x-icon name="alert" class="size-4" /> {{ session('error') }}</div>
        @endif
        {{ $slot }}
    </main>

    <footer class="py-4 text-center text-xs text-slate-400">{{ $appearance['app_name'] }} · Sistema de Gerenciamento de Atendimento</footer>
</div>

{{-- Toasts: $this->dispatch('toast', type: 'success', message: '...') --}}
<div x-data="{ toasts: [] }"
     @toast.window="const t = { id: Date.now() + Math.random(), ...$event.detail }; toasts.push(t); setTimeout(() => toasts = toasts.filter(x => x.id !== t.id), 4000)"
     x-init="@if (session('success')) toasts.push({ id: 1, type: 'success', message: @js(session('success')) }); setTimeout(() => toasts = [], 4000) @endif"
     class="pointer-events-none fixed right-4 bottom-4 z-50 flex w-80 flex-col gap-2">
    <template x-for="t in toasts" :key="t.id">
        <div x-transition class="pointer-events-auto flex items-start gap-2 rounded-xl border px-4 py-3 text-sm shadow-lg"
             :class="t.type === 'error' ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200' : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'">
            <span x-text="t.message"></span>
        </div>
    </template>
</div>

@livewireScripts
</body>
</html>
