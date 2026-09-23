@props(['title' => null, 'maxWidth' => 'max-w-lg'])
<div x-data="{ show: @entangle($attributes->wire('model')) }"
     x-show="show" x-cloak
     x-on:keydown.escape.window="show = false"
     class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto p-4 sm:items-center">
    <div x-show="show" x-transition.opacity class="fixed inset-0 bg-slate-900/50" @click="show = false"></div>
    <div x-show="show" x-transition class="relative w-full {{ $maxWidth }} rounded-2xl bg-white shadow-xl dark:bg-slate-900">
        @if ($title)
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5 dark:border-slate-800">
                <h3 class="font-semibold">{{ $title }}</h3>
                <button type="button" class="btn btn-ghost !p-1.5" @click="show = false"><x-icon name="x" class="size-4" /></button>
            </div>
        @endif
        <div class="p-5">{{ $slot }}</div>
        @isset($footer)
            <div class="flex justify-end gap-2 rounded-b-2xl border-t border-slate-200 bg-slate-50 px-5 py-3 dark:border-slate-800 dark:bg-slate-900/60">{{ $footer }}</div>
        @endisset
    </div>
</div>
