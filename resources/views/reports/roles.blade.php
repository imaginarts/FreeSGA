<x-report :title="$title" :unit="$unit">
    <thead><tr><th>Perfil</th><th>Descrição</th><th>Módulos</th></tr></thead>
    <tbody>
    @foreach ($rows as $r)
        <tr>
            <td>{{ $r->name }}</td>
            <td>{{ $r->description }}</td>
            <td>{{ collect($r->modules)->map(fn ($m) => \App\Enums\Module::tryFrom($m)?->label() ?? $m)->join(', ') }}</td>
        </tr>
    @endforeach
    </tbody>
</x-report>
