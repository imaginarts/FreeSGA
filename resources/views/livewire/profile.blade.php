<div class="mx-auto max-w-3xl">
    <x-page-header title="Meu perfil" :subtitle="'Login: '.auth()->user()->login" />

    <div class="space-y-6">
        <form wire:submit="save" class="card">
            <div class="card-header"><h2 class="card-title">Dados pessoais</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <x-field label="Nome" error="name"><input class="input" wire:model="name"></x-field>
                <x-field label="Sobrenome" error="last_name"><input class="input" wire:model="last_name"></x-field>
                <x-field label="E-mail" error="email" class="sm:col-span-2"><input type="email" class="input" wire:model="email"></x-field>
            </div>
            <div class="flex justify-end border-t border-slate-200 px-5 py-3 dark:border-slate-800"><button class="btn btn-primary">Salvar</button></div>
        </form>

        <form wire:submit="changePassword" class="card">
            <div class="card-header"><h2 class="card-title">Alterar senha</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-3">
                <x-field label="Senha atual" error="current_password"><input type="password" class="input" wire:model="current_password" autocomplete="current-password"></x-field>
                <x-field label="Nova senha" error="password"><input type="password" class="input" wire:model="password" autocomplete="new-password"></x-field>
                <x-field label="Confirmação"><input type="password" class="input" wire:model="password_confirmation" autocomplete="new-password"></x-field>
            </div>
            <div class="flex justify-end border-t border-slate-200 px-5 py-3 dark:border-slate-800"><button class="btn btn-primary">Alterar senha</button></div>
        </form>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Minhas lotações</h2></div>
            <table class="table">
                <thead><tr><th>Unidade</th><th>Perfil</th></tr></thead>
                <tbody>
                @forelse ($allocations as $a)
                    <tr><td>{{ $a->unit->name }}</td><td>{{ $a->role->name }}</td></tr>
                @empty
                    <tr><td colspan="2" class="text-center text-slate-500">{{ auth()->user()->is_admin ? 'Administrador: acesso a todas as unidades.' : 'Nenhuma lotação.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
