<x-report :title="$title" :unit="$unit" :start="$start" :end="$end" :uses-period="$usesPeriod" :filter-user="$filterUser">
    <thead><tr><th>Senha</th><th>Data</th><th>Chegada</th><th>Chamada</th><th>Fim</th><th>Serviço</th><th>Status</th><th>Cliente</th><th>Atendente</th></tr></thead>
    <tbody>
    @forelse ($rows as $t)
        <tr>
            <td class="font-mono font-semibold">{{ $t->code() }}</td>
            <td>{{ $t->localTime($t->arrived_at, 'd/m/Y') }}</td>
            <td>{{ $t->localTime($t->arrived_at, 'H:i:s') }}</td>
            <td>{{ $t->localTime($t->called_at, 'H:i:s') }}</td>
            <td>{{ $t->localTime($t->finished_at, 'H:i:s') }}</td>
            <td>{{ $t->service->name }}</td>
            <td>{{ $t->status->label() }}</td>
            <td>{{ $t->customer?->name }}</td>
            <td>{{ $t->user?->login }}</td>
        </tr>
    @empty
        <tr><td colspan="9" class="text-center">Nenhuma senha no período.</td></tr>
    @endforelse
    </tbody>
    <tfoot><tr><td colspan="9">Total: {{ $rows->count() }}</td></tr></tfoot>
</x-report>
