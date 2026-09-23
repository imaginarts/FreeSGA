<div>
    <x-page-header title="Clientes">
        <input class="input w-64" placeholder="Nome, documento ou e-mail…" wire:model.live.debounce.300ms="search">
        <button class="btn btn-primary" wire:click="create"><x-icon name="plus" class="size-4" /> Novo cliente</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Nome</th><th>Documento</th><th>Contato</th><th>Senhas</th><th></th></tr></thead>
            <tbody>
            @forelse ($customers as $c)
                <tr wire:key="c-{{ $c->id }}">
                    <td class="font-medium">{{ $c->name }}</td>
                    <td class="font-mono text-xs">{{ \App\Support\Privacy::document($c->document) }}</td>
                    <td class="text-xs">{{ $c->email }}<br>{{ $c->phone }}</td>
                    <td>{{ $c->tickets_count }}</td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn btn-ghost btn-sm" wire:click="history({{ $c->id }})" title="Histórico"><x-icon name="clock" class="size-4" /></button>
                        <button class="btn btn-ghost btn-sm" wire:click="exportData({{ $c->id }})" title="Exportar dados do titular (LGPD)"><x-icon name="external" class="size-4" /></button>
                        @unless (str_starts_with($c->document, \App\Services\PrivacyService::ANONYMIZED_PREFIX))
                            <button class="btn btn-ghost btn-sm text-red-600" wire:click="anonymize({{ $c->id }})" wire:confirm="Anonimizar os dados pessoais de {{ $c->name }}? Nome, documento, contatos e endereço serão apagados de forma irreversível. O histórico estatístico é mantido." title="Anonimizar (LGPD)"><x-icon name="user-x" class="size-4" /></button>
                        @endunless
                        <button class="btn btn-ghost btn-sm" wire:click="edit({{ $c->id }})"><x-icon name="pencil" class="size-4" /></button>
                        <button class="btn btn-ghost btn-sm text-red-600" wire:click="delete({{ $c->id }})" wire:confirm="Remover o cliente {{ $c->name }}?"><x-icon name="trash" class="size-4" /></button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-slate-500">Nenhum cliente encontrado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar cliente' : 'Novo cliente'" max-width="max-w-3xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-field label="Nome" error="form.name" class="sm:col-span-2"><input class="input" wire:model="form.name"></x-field>
                <x-field label="Documento (CPF/CNPJ)" error="form.document"><input class="input" wire:model="form.document"></x-field>
                <x-field label="E-mail" error="form.email"><input type="email" class="input" wire:model="form.email"></x-field>
                <x-field label="Telefone" error="form.phone"><input class="input" wire:model="form.phone"></x-field>
                <div class="grid grid-cols-2 gap-3">
                    <x-field label="Nascimento" error="form.birth_date"><input type="date" class="input" wire:model="form.birth_date"></x-field>
                    <x-field label="Gênero">
                        <select class="input" wire:model="form.gender"><option value="">—</option><option value="F">Feminino</option><option value="M">Masculino</option><option value="O">Outro</option></select>
                    </x-field>
                </div>
            </div>
            <p class="border-t border-slate-200 pt-3 text-sm font-medium dark:border-slate-800">Endereço</p>
            <div class="grid gap-3 sm:grid-cols-6">
                <x-field label="CEP" class="sm:col-span-2"><input class="input" wire:model="form.address_zip"></x-field>
                <x-field label="Logradouro" class="sm:col-span-4"><input class="input" wire:model="form.address_street"></x-field>
                <x-field label="Número"><input class="input" wire:model="form.address_number"></x-field>
                <x-field label="Complemento" class="sm:col-span-2"><input class="input" wire:model="form.address_complement"></x-field>
                <x-field label="Cidade" class="sm:col-span-2"><input class="input" wire:model="form.address_city"></x-field>
                <x-field label="UF"><input class="input uppercase" maxlength="3" wire:model="form.address_state"></x-field>
            </div>
            <x-field label="Observações"><textarea class="input" rows="2" wire:model="form.notes"></textarea></x-field>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </x-modal>

    <x-modal wire:model="showHistory" :title="'Histórico de senhas'.($historyCustomer ? ' · '.$historyCustomer->name : '')" max-width="max-w-3xl">
        <div class="max-h-96 overflow-y-auto">
            <table class="table">
                <thead><tr><th>Senha</th><th>Unidade</th><th>Serviço</th><th>Data</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($historyTickets as $t)
                    <tr>
                        <td class="font-mono font-semibold">{{ $t->code() }}</td>
                        <td>{{ $t->unit->name }}</td>
                        <td>{{ $t->service->name }}</td>
                        <td>{{ $t->localTime($t->arrived_at, 'd/m/Y H:i') }}</td>
                        <td><x-status-badge :status="$t->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-500">Nenhuma senha registrada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-modal>
</div>
