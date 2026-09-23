<x-report :title="$title" :unit="$unit">
    <thead><tr><th>Sigla</th><th>Serviço</th><th>Subserviços</th><th>Tipo</th><th>Departamento</th></tr></thead>
    <tbody>
    @forelse ($rows as $us)
        <tr>
            <td class="font-mono font-semibold">{{ $us->prefix }}</td>
            <td>{{ $us->service->name }}</td>
            <td>{{ $us->service->children->pluck('name')->join(', ') ?: '—' }}</td>
            <td>{{ $us->type->label() }}</td>
            <td>{{ $us->department?->name ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="text-center">Nenhum serviço ativo.</td></tr>
    @endforelse
    </tbody>
</x-report>
