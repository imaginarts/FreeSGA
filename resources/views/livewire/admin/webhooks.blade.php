<x-admin-shell>
    <x-page-header title="Webhooks" subtitle="Notifica sistemas externos (POST JSON) a cada evento de senha">
        <button class="btn btn-primary" wire:click="create"><x-icon name="plus" class="size-4" /> Novo webhook</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nome</th><th>URL</th><th>Eventos</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($webhooks as $w)
                <tr wire:key="w-{{ $w->id }}">
                    <td class="font-medium">{{ $w->name }}</td>
                    <td class="max-w-64 truncate font-mono text-xs">{{ $w->url }}</td>
                    <td>{{ count($w->events ?? []) }}</td>
                    <td><x-active-badge :active="$w->enabled" /></td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn btn-ghost btn-sm" wire:click="edit({{ $w->id }})"><x-icon name="pencil" class="size-4" /></button>
                        <button class="btn btn-ghost btn-sm text-red-600" wire:click="delete({{ $w->id }})" wire:confirm="Remover o webhook {{ $w->name }}?"><x-icon name="trash" class="size-4" /></button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-slate-500">Nenhum webhook cadastrado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card card-body mt-6 text-sm text-slate-600 dark:text-slate-400">
        <p class="font-medium text-slate-800 dark:text-slate-200">Como funciona</p>
        <p class="mt-1">Cada evento gera um <code>POST</code> com o JSON da senha para a URL configurada, com o cabeçalho <code>X-Webhook-Event</code>. O envio é feito pela fila (<code>php artisan queue:work</code>), com até 3 tentativas.</p>
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar webhook' : 'Novo webhook'" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field label="Nome" error="form.name"><input class="input" wire:model="form.name"></x-field>
                <x-field label="URL" error="form.url"><input class="input" wire:model="form.url" placeholder="https://"></x-field>
            </div>
            <x-field label="Eventos" error="form.events">
                <div class="grid gap-1.5 sm:grid-cols-2">
                    @foreach ($events as $e)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" value="{{ $e->value }}" wire:model="form.events"> {{ $e->label() }} <code class="text-xs text-slate-400">{{ $e->value }}</code></label>
                    @endforeach
                </div>
            </x-field>
            <x-field label="Cabeçalhos HTTP">
                <div class="space-y-2">
                    @foreach ($form['headers'] ?? [] as $i => $h)
                        <div class="flex gap-2" wire:key="h-{{ $i }}">
                            <input class="input" placeholder="Authorization" wire:model="form.headers.{{ $i }}.key">
                            <input class="input" placeholder="Bearer …" wire:model="form.headers.{{ $i }}.value">
                            <button type="button" class="btn btn-ghost" wire:click="removeHeader({{ $i }})"><x-icon name="x" class="size-4" /></button>
                        </div>
                    @endforeach
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addHeader"><x-icon name="plus" class="size-3.5" /> Adicionar cabeçalho</button>
                </div>
            </x-field>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="form.enabled"> Webhook ativo</label>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </x-modal>
</x-admin-shell>
