<x-report :title="$title" :unit="$unit" :start="$start" :end="$end" :uses-period="$usesPeriod" :filter-user="$filterUser">
    <thead><tr><th>Dia</th><th class="text-right">Senhas</th><th class="text-right">Encerradas</th><th class="text-right">Não compareceu</th><th class="text-right">Canceladas</th><th>Espera média</th><th>Atendimento médio</th></tr></thead>
    <tbody>
    @forelse ($rows as $day => $r)
        <tr>
            <td>{{ \Carbon\Carbon::parse($day)->translatedFormat('d/m/Y (D)') }}</td>
            <td class="text-right">{{ $r['total'] }}</td>
            <td class="text-right">{{ $r['finished'] }}</td>
            <td class="text-right">{{ $r['no_show'] }}</td>
            <td class="text-right">{{ $r['cancelled'] }}</td>
            <td>{{ \App\Models\Ticket::formatSeconds($r['wait']) }}</td>
            <td>{{ \App\Models\Ticket::formatSeconds($r['service']) }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center">Sem movimento no período.</td></tr>
    @endforelse
    </tbody>
    <tfoot><tr><td>Total</td><td class="text-right">{{ $rows->sum('total') }}</td><td class="text-right">{{ $rows->sum('finished') }}</td><td class="text-right">{{ $rows->sum('no_show') }}</td><td class="text-right">{{ $rows->sum('cancelled') }}</td><td colspan="2"></td></tr></tfoot>
</x-report>
