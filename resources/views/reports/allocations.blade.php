<x-report :title="$title" :unit="$unit">
    <thead><tr><th>Login</th><th>Nome</th><th>Perfil</th><th>Serviços</th><th>Situação</th></tr></thead>
    <tbody>
    @forelse ($rows as $r)
        <tr>
            <td class="font-mono">{{ $r['allocation']->user->login }}</td>
            <td>{{ $r['allocation']->user->fullName() }}</td>
            <td>{{ $r['allocation']->role->name }}</td>
            <td>{{ $r['services']->join(', ') ?: '—' }}</td>
            <td>{{ $r['allocation']->user->active ? 'Ativo' : 'Inativo' }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="text-center">Nenhum usuário lotado.</td></tr>
    @endforelse
    </tbody>
</x-report>
