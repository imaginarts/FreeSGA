<x-report :title="$title" :unit="$unit">
    <thead><tr><th>Serviço</th><th>Subserviços</th><th>Situação</th></tr></thead>
    <tbody>
    @foreach ($rows as $s)
        <tr>
            <td>{{ $s->name }}</td>
            <td>{{ $s->children->map(fn ($c) => $c->name.($c->active ? '' : ' (inativo)'))->join(', ') ?: '—' }}</td>
            <td>{{ $s->active ? 'Ativo' : 'Inativo' }}</td>
        </tr>
    @endforeach
    </tbody>
</x-report>
