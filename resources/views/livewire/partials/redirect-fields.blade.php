<div class="grid gap-3 sm:grid-cols-2">
    <x-field label="Serviço de destino" error="redirectServiceId">
        <select class="input" wire:model.live="redirectServiceId">
            <option value="">Selecione…</option>
            @foreach ($this->redirectServices as $us)<option value="{{ $us->service_id }}">{{ $us->prefix }} - {{ $us->service->name }}</option>@endforeach
        </select>
    </x-field>
    <x-field label="Atendente (opcional)">
        <select class="input" wire:model="redirectUserId" @disabled(! $redirectServiceId)>
            <option value="">Qualquer atendente</option>
            @foreach ($this->redirectUsers as $u)<option value="{{ $u->id }}">{{ $u->fullName() }}</option>@endforeach
        </select>
    </x-field>
</div>
