<x-report :title="$title" :unit="$unit" :start="$start" :end="$end" :uses-period="$usesPeriod" :filter-user="$filterUser">
    <thead><tr><th>Serviço</th><th class="w-32 text-right">Total</th></tr></thead>
    <tbody>
    @forelse ($rows as $r)
        <tr><td>{{ $r->name }}</td><td class="text-right">{{ $r->total }}</td></tr>
    @empty
        <tr><td colspan="2" class="text-center">Nenhum serviço realizado no período.</td></tr>
    @endforelse
    </tbody>
    <tfoot><tr><td>Total</td><td class="text-right">{{ $rows->sum('total') }}</td></tr></tfoot>
</x-report>
