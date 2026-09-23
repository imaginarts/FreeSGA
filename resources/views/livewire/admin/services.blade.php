<x-admin-shell>
    <x-page-header title="Serviços" subtitle="Catálogo global; cada unidade habilita os seus em Configurações da unidade">
        <input class="input w-56" placeholder="Buscar…" wire:model.live.debounce.300ms="search">
        <button class="btn btn-primary" wire:click="create"><x-icon name="plus" class="size-4" /> Novo serviço</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Serviço</th><th>Peso</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($services as $s)
                <tr wire:key="s-{{ $s->id }}">
                    <td><p class="font-medium">{{ $s->name }}</p><p class="text-xs text-slate-500">{{ $s->description }}</p></td>
                    <td>{{ $s->weight }}</td>
                    <td><x-active-badge :active="$s->active" /></td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn btn-ghost btn-sm" wire:click="createChild({{ $s->id }})" title="Adicionar subserviço"><x-icon name="plus" class="size-4" /></button>
                        <button class="btn btn-ghost btn-sm" wire:click="edit({{ $s->id }})"><x-icon name="pencil" class="size-4" /></button>
                        <button class="btn btn-ghost btn-sm text-red-600" wire:click="delete({{ $s->id }})" wire:confirm="Remover o serviço {{ $s->name }}?"><x-icon name="trash" class="size-4" /></button>
                    </td>
                </tr>
                @foreach ($s->children as $c)
                    <tr wire:key="c-{{ $c->id }}" class="bg-slate-50/50 dark:bg-slate-900/40">
                        <td class="pl-10"><span class="text-slate-400">↳</span> {{ $c->name }}</td>
                        <td>{{ $c->weight }}</td>
                        <td><x-active-badge :active="$c->active" /></td>
                        <td class="text-right whitespace-nowrap">
                            <button class="btn btn-ghost btn-sm" wire:click="edit({{ $c->id }})"><x-icon name="pencil" class="size-4" /></button>
                            <button class="btn btn-ghost btn-sm text-red-600" wire:click="delete({{ $c->id }})" wire:confirm="Remover o subserviço {{ $c->name }}?"><x-icon name="trash" class="size-4" /></button>
                        </td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="4" class="text-center text-slate-500">Nenhum serviço cadastrado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $services->links() }}</div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar serviço' : 'Novo serviço'">
        <form wire:submit="save" class="space-y-4">
            <x-field label="Nome" error="form.name"><input class="input" wire:model="form.name"></x-field>
            <x-field label="Descrição" error="form.description"><input class="input" wire:model="form.description"></x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Serviço principal" error="form.parent_id" hint="Deixe vazio para serviço principal">
                    <select class="input" wire:model="form.parent_id">
                        <option value="">—</option>
                        @foreach ($parents as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Peso" error="form.weight" hint="Usado na ordenação da fila"><input type="number" class="input" wire:model="form.weight"></x-field>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="form.active"> Serviço ativo</label>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </x-modal>
</x-admin-shell>
