<x-report :title="$title" :unit="$unit" :start="$start" :end="$end" :uses-period="$usesPeriod" :filter-user="$filterUser">
    <thead><tr><th>Senha</th><th>Data</th><th>Chegada</th><th>Chamada</th><th>Início</th><th>Fim</th><th>Espera</th><th>Duração</th><th>Serviço</th><th>Atendente</th></tr></thead>
    <tbody>
    @forelse ($rows as $t)
        <tr>
            <td class="font-mono font-semibold">{{ $t->code() }}</td>
            <td>{{ $t->localTime($t->arrived_at, 'd/m/Y') }}</td>
            <td>{{ $t->localTime($t->arrived_at, 'H:i:s') }}</td>
            <td>{{ $t->localTime($t->called_at, 'H:i:s') }}</td>
            <td>{{ $t->localTime($t->started_at, 'H:i:s') }}</td>
            <td>{{ $t->localTime($t->finished_at, 'H:i:s') }}</td>
            <td>{{ \App\Models\Ticket::formatSeconds($t->wait_time) }}</td>
            <td>{{ \App\Models\Ticket::formatSeconds($t->service_time) }}</td>
            <td>{{ $t->service->name }}</td>
            <td>{{ $t->user?->login }}</td>
        </tr>
    @empty
        <tr><td colspan="10" class="text-center">Nenhum atendimento encerrado no período.</td></tr>
    @endforelse
    </tbody>
    <tfoot><tr><td colspan="10">Total: {{ $rows->count() }}</td></tr></tfoot>
</x-report>
