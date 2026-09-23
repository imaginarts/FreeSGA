<x-admin-shell>
    <x-page-header title="API" subtitle="Tokens de acesso para integrações (totens, apps, sistemas externos)">
        <button class="btn btn-primary" wire:click="create"><x-icon name="plus" class="size-4" /> Novo token</button>
    </x-page-header>

    @if ($plainToken)
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/40" x-data>
            <p class="text-sm font-medium text-amber-900 dark:text-amber-200">Copie o token agora. Ele não será exibido novamente.</p>
            <div class="mt-2 flex gap-2">
                <input class="input font-mono" readonly value="{{ $plainToken }}" x-ref="token">
                <button class="btn btn-secondary" @click="navigator.clipboard.writeText($refs.token.value); $dispatch('toast', { type: 'success', message: 'Copiado!' })"><x-icon name="copy" class="size-4" /></button>
            </div>
        </div>
    @endif

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Descrição</th><th>Usuário</th><th>Criado em</th><th>Último uso</th><th></th></tr></thead>
            <tbody>
            @forelse ($tokens as $t)
                <tr wire:key="t-{{ $t->id }}">
                    <td class="font-medium">{{ $t->name }}</td>
                    <td>{{ $t->tokenable?->login }}</td>
                    <td>{{ $t->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $t->last_used_at?->diffForHumans() ?? 'Nunca' }}</td>
                    <td class="text-right"><button class="btn btn-ghost btn-sm text-red-600" wire:click="revoke({{ $t->id }})" wire:confirm="Revogar este token?"><x-icon name="trash" class="size-4" /></button></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-slate-500">Nenhum token criado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card card-body mt-6 text-sm text-slate-600 dark:text-slate-400">
        <p class="font-medium text-slate-800 dark:text-slate-200">Uso</p>
        <p class="mt-1">Envie o cabeçalho <code>Authorization: Bearer &lt;token&gt;</code>. As permissões seguem as do usuário dono do token. Endpoints em <code>/api/v1</code>, por exemplo:</p>
        <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">curl -X POST {{ url('/api/v1/tickets') }} \
  -H "Authorization: Bearer SEU_TOKEN" -H "Accept: application/json" \
  -d unit_id=1 -d service_id=1 -d priority_id=1</pre>
    </div>

    <x-modal wire:model="showForm" title="Novo token">
        <form wire:submit="save" class="space-y-4">
            <x-field label="Descrição" error="name"><input class="input" wire:model="name" placeholder="Ex.: Totem da recepção"></x-field>
            <x-field label="Usuário dono do token" error="userId">
                <select class="input" wire:model="userId">
                    @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->fullName() }} ({{ $u->login }})</option>@endforeach
                </select>
            </x-field>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Gerar token</button>
            </div>
        </form>
    </x-modal>
</x-admin-shell>
