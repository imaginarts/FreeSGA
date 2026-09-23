@php($waited = (int) $t->arrived_at->diffInSeconds(now(), true))
<button wire:key="{{ $key }}-{{ $t->id }}" wire:click="open({{ $t->id }})"
        title="{{ $t->priority->name }} · {{ $t->service->name }} · aguardando {{ intdiv($waited, 60) }} min{{ $state === 'breach' ? ' (acima da meta)' : '' }}"
        @class(['flex items-center gap-1.5 rounded-lg border px-2.5 py-1 font-mono text-sm font-bold hover:shadow', \App\Services\SlaService::classes($state)])
        style="color: {{ $t->priority->color }}; border-color: {{ $t->priority->color }}40">
    {{ $t->code() }}
    @if ($state)
        <span @class(['text-[10px] font-medium', 'text-red-600' => $state === 'breach', 'text-amber-600' => $state === 'warning', 'text-slate-400' => $state === 'ok'])>{{ intdiv($waited, 60) }}m</span>
    @endif
</button>
