<x-admin-shell>
    <x-page-header title="Unidades" subtitle="Locais físicos onde as senhas são emitidas e atendidas">
        <input class="input w-56" placeholder="Buscar…" wire:model.live.debounce.300ms="search">
        <button class="btn btn-primary" wire:click="create"><x-icon name="plus" class="size-4" /> Nova unidade</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nome</th><th>Fuso horário</th><th>Serviços ativos</th><th>Usuários</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($units as $u)
                <tr wire:key="u-{{ $u->id }}">
                    <td><p class="font-medium">{{ $u->name }}</p><p class="text-xs text-slate-500">{{ $u->description }}</p></td>
                    <td>{{ $u->timezone() }}</td>
                    <td>{{ $u->unit_services_count }}</td>
                    <td>{{ $u->allocations_count }}</td>
                    <td><x-active-badge :active="$u->active" /></td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn btn-ghost btn-sm" wire:click="edit({{ $u->id }})"><x-icon name="pencil" class="size-4" /></button>
                        <button class="btn btn-ghost btn-sm text-red-600" wire:click="delete({{ $u->id }})" wire:confirm="Remover a unidade {{ $u->name }}?"><x-icon name="trash" class="size-4" /></button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-slate-500">Nenhuma unidade cadastrada.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $units->links() }}</div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar unidade' : 'Nova unidade'">
        <form wire:submit="save" class="space-y-4">
            <x-field label="Nome" error="form.name"><input class="input" wire:model="form.name"></x-field>
            <x-field label="Descrição" error="form.description"><input class="input" wire:model="form.description"></x-field>
            <x-field label="Fuso horário" error="form.timezone">
                <select class="input" wire:model="form.timezone">
                    <option value="">Padrão do servidor</option>
                    @foreach ($timezones as $tz)<option value="{{ $tz }}">{{ $tz }}</option>@endforeach
                </select>
            </x-field>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="form.active"> Unidade ativa</label>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </x-modal>
</x-admin-shell>
