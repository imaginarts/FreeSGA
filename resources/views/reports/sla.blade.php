@php($fmt = fn ($s) => \App\Models\Ticket::formatSeconds($s))
<x-report :title="$title" :unit="$unit" :start="$start" :end="$end" :uses-period="$usesPeriod" :filter-user="$filterUser">
    <thead><tr><th>Serviço</th><th>Meta</th><th class="text-right">Chamadas</th><th class="text-right">Dentro da meta</th><th class="text-right">%</th><th>Espera média</th><th>Maior espera</th></tr></thead>
    <tbody>
    @forelse ($rows as $r)
        <tr>
            <td>{{ $r['service'] }}</td>
            <td>{{ $r['target'] ? intdiv($r['target'], 60).' min' : '—' }}</td>
            <td class="text-right">{{ $r['total'] }}</td>
            <td class="text-right">{{ $r['within'] ?? '—' }}</td>
            <td @class(['text-right font-semibold', 'text-red-600' => $r['percent'] !== null && $r['percent'] < 80])>{{ $r['percent'] !== null ? number_format($r['percent'], 1, ',', '.').'%' : '—' }}</td>
            <td>{{ $fmt($r['avg']) }}</td>
            <td>{{ $fmt($r['max']) }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center">Nenhuma senha chamada no período.</td></tr>
    @endforelse
    </tbody>
    @if ($rows->isNotEmpty())
        @php($total = $rows->sum('total'))
        @php($withTarget = $rows->whereNotNull('within'))
        <tfoot><tr>
            <td colspan="2">Geral</td>
            <td class="text-right">{{ $total }}</td>
            <td class="text-right">{{ $withTarget->sum('within') }}</td>
            <td class="text-right">{{ $withTarget->sum('total') ? number_format($withTarget->sum('within') / $withTarget->sum('total') * 100, 1, ',', '.').'%' : '—' }}</td>
            <td colspan="2">{{ $fmt((int) ($rows->sum(fn ($r) => $r['avg'] * $r['total']) / max(1, $total))) }}</td>
        </tr></tfoot>
    @endif
    <caption class="caption-bottom pt-3 text-left text-xs text-slate-500">Considera a espera entre a emissão e a primeira chamada. Metas definidas em Configurações da unidade.</caption>
</x-report>
