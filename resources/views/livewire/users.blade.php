<div>
    <x-page-header title="Usuários" subtitle="Cadastro de atendentes e suas lotações (unidade + perfil)">
        <input class="input w-56" placeholder="Buscar…" wire:model.live.debounce.300ms="search">
        <button class="btn btn-primary" wire:click="create"><x-icon name="plus" class="size-4" /> Novo usuário</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Usuário</th><th>Login</th><th>Lotações</th><th>Último acesso</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($users as $u)
                <tr wire:key="u-{{ $u->id }}">
                    <td class="font-medium">{{ $u->fullName() }} @if ($u->is_admin)<span class="badge bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">admin</span>@endif</td>
                    <td class="font-mono text-xs">{{ $u->login }}</td>
                    <td class="text-xs">@foreach ($u->allocations as $a){{ $a->unit->name }} <span class="text-slate-400">({{ $a->role->name }})</span>@if (! $loop->last)<br>@endif @endforeach</td>
                    <td class="text-xs">{{ $u->last_login_at?->diffForHumans() ?? 'Nunca' }}</td>
                    <td><x-active-badge :active="$u->active" /></td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn btn-ghost btn-sm" wire:click="openPassword({{ $u->id }})" title="Alterar senha"><x-icon name="key" class="size-4" /></button>
                        <button class="btn btn-ghost btn-sm" wire:click="edit({{ $u->id }})"><x-icon name="pencil" class="size-4" /></button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-slate-500">Nenhum usuário encontrado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar usuário' : 'Novo usuário'" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field label="Login" error="form.login"><input class="input" wire:model="form.login" autocomplete="off"></x-field>
                <x-field label="E-mail" error="form.email"><input type="email" class="input" wire:model="form.email"></x-field>
                <x-field label="Nome" error="form.name"><input class="input" wire:model="form.name"></x-field>
                <x-field label="Sobrenome" error="form.last_name"><input class="input" wire:model="form.last_name"></x-field>
                @unless ($editingId)
                    <x-field label="Senha" error="form.password"><input type="password" class="input" wire:model="form.password" autocomplete="new-password"></x-field>
                    <x-field label="Confirmação"><input type="password" class="input" wire:model="form.password_confirmation" autocomplete="new-password"></x-field>
                @endunless
            </div>
            <div class="flex gap-6">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="form.active"> Ativo</label>
                @if (auth()->user()->is_admin)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="form.is_admin"> Administrador do sistema</label>
                @endif
            </div>

            <x-field label="Lotações" error="allocations">
                <div class="space-y-2">
                    @foreach ($allocations as $i => $a)
                        <div class="flex gap-2" wire:key="al-{{ $i }}">
                            <select class="input" wire:model="allocations.{{ $i }}.unit_id">
                                @foreach ($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach
                            </select>
                            <select class="input" wire:model="allocations.{{ $i }}.role_id">
                                @foreach ($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                            </select>
                            <button type="button" class="btn btn-ghost" wire:click="removeAllocation({{ $i }})"><x-icon name="x" class="size-4" /></button>
                        </div>
                        @error("allocations.$i.unit_id")<p class="error">{{ $message }}</p>@enderror
                    @endforeach
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addAllocation"><x-icon name="plus" class="size-3.5" /> Adicionar lotação</button>
                </div>
            </x-field>
            <p class="text-xs text-slate-500">Os serviços que o usuário atende são definidos em <b>Configurações da unidade → Atendentes</b>.</p>

            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </x-modal>

    <x-modal wire:model="showPassword" title="Alterar senha do usuário">
        <form wire:submit="savePassword" class="space-y-4">
            <x-field label="Nova senha" error="password"><input type="password" class="input" wire:model="password" autocomplete="new-password"></x-field>
            <x-field label="Confirmação"><input type="password" class="input" wire:model="password_confirmation" autocomplete="new-password"></x-field>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </x-modal>
</div>
