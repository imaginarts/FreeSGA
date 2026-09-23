<x-admin-shell>
    <x-page-header title="Perfis" subtitle="Definem os módulos liberados ao usuário em cada unidade (lotação)">
        <button class="btn btn-primary" wire:click="create"><x-icon name="plus" class="size-4" /> Novo perfil</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Perfil</th><th>Módulos</th><th>Lotações</th><th></th></tr></thead>
            <tbody>
            @forelse ($roles as $r)
                <tr wire:key="r-{{ $r->id }}">
                    <td><p class="font-medium">{{ $r->name }}</p><p class="text-xs text-slate-500">{{ $r->description }}</p></td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($r->modules ?? [] as $m)
                                <span class="badge bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ \App\Enums\Module::tryFrom($m)?->label() ?? $m }}</span>
                            @endforeach
                        </div>
                    </td>
                    <td>{{ $r->allocations_count }}</td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn btn-ghost btn-sm" wire:click="edit({{ $r->id }})"><x-icon name="pencil" class="size-4" /></button>
                        <button class="btn btn-ghost btn-sm text-red-600" wire:click="delete({{ $r->id }})" wire:confirm="Remover o perfil {{ $r->name }}?"><x-icon name="trash" class="size-4" /></button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-slate-500">Nenhum perfil cadastrado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar perfil' : 'Novo perfil'" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field label="Nome" error="form.name"><input class="input" wire:model="form.name"></x-field>
                <x-field label="Descrição" error="form.description"><input class="input" wire:model="form.description"></x-field>
            </div>
            <x-field label="Módulos">
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($modules as $m)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 has-checked:border-brand-500 has-checked:bg-brand-50 dark:border-slate-700 dark:has-checked:bg-brand-900/30">
                            <input type="checkbox" class="checkbox mt-0.5" value="{{ $m->value }}" wire:model="form.modules">
                            <span><span class="block text-sm font-medium">{{ $m->label() }}</span><span class="block text-xs text-slate-500">{{ $m->description() }}</span></span>
                        </label>
                    @endforeach
                </div>
            </x-field>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </x-modal>
</x-admin-shell>
