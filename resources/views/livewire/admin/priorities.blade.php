<x-admin-shell>
    <x-page-header title="Prioridades" subtitle="Peso 0 é atendimento normal; quanto maior o peso, maior a prioridade">
        <button class="btn btn-primary" wire:click="create"><x-icon name="plus" class="size-4" /> Nova prioridade</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nome</th><th>Cor</th><th>Peso</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($priorities as $p)
                <tr wire:key="p-{{ $p->id }}">
                    <td><p class="font-medium">{{ $p->name }}</p><p class="text-xs text-slate-500">{{ $p->description }}</p></td>
                    <td><span class="inline-block size-5 rounded-md border border-black/10" style="background: {{ $p->color }}"></span></td>
                    <td>{{ $p->weight }} @if ($p->weight === 0)<span class="text-xs text-slate-500">(normal)</span>@endif</td>
                    <td><x-active-badge :active="$p->active" /></td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn btn-ghost btn-sm" wire:click="edit({{ $p->id }})"><x-icon name="pencil" class="size-4" /></button>
                        <button class="btn btn-ghost btn-sm text-red-600" wire:click="delete({{ $p->id }})" wire:confirm="Remover a prioridade {{ $p->name }}?"><x-icon name="trash" class="size-4" /></button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar prioridade' : 'Nova prioridade'">
        <form wire:submit="save" class="space-y-4">
            <x-field label="Nome" error="form.name"><input class="input" wire:model="form.name"></x-field>
            <x-field label="Descrição" error="form.description"><input class="input" wire:model="form.description"></x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Peso" error="form.weight"><input type="number" min="0" class="input" wire:model="form.weight"></x-field>
                <x-field label="Cor" error="form.color">
                    <div class="flex gap-2"><input type="color" class="h-9 w-12 rounded border border-slate-300" wire:model.live="form.color"><input class="input font-mono" wire:model.live="form.color"></div>
                </x-field>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="form.active"> Prioridade ativa</label>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </x-modal>
</x-admin-shell>
