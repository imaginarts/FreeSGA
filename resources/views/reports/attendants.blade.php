@php($fmt = fn ($s) => \App\Models\Ticket::formatSeconds($s === null ? null : (int) $s))
<x-report :title="$title" :unit="$unit" :start="$start" :end="$end" :uses-period="$usesPeriod">
    <thead><tr><th>Atendente</th><th class="text-right">Atendimentos</th><th class="text-right">Não compareceu</th><th>TME</th><th>TMD</th><th>TMA</th><th>Permanência</th></tr></thead>
    <tbody>
    @forelse ($rows as $r)
        <tr>
            <td>{{ trim($r->name.' '.$r->last_name) }}</td>
            <td class="text-right">{{ $r->total }}</td>
            <td class="text-right">{{ $r->no_show }}</td>
            <td>{{ $fmt($r->wait) }}</td>
            <td>{{ $fmt($r->travel) }}</td>
            <td>{{ $fmt($r->service) }}</td>
            <td>{{ $fmt($r->total_time) }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center">Nenhum atendimento no período.</td></tr>
    @endforelse
    </tbody>
    @if ($rows->isNotEmpty())
        @php($sum = $rows->sum('total'))
        @php($weighted = fn ($col) => $sum ? $rows->sum(fn ($r) => $r->{$col} * $r->total) / $sum : null)
        <tfoot><tr><td>Geral</td><td class="text-right">{{ $sum }}</td><td class="text-right">{{ $rows->sum('no_show') }}</td><td>{{ $fmt($weighted('wait')) }}</td><td>{{ $fmt($weighted('travel')) }}</td><td>{{ $fmt($weighted('service')) }}</td><td>{{ $fmt($weighted('total_time')) }}</td></tr></tfoot>
    @endif
    <caption class="caption-bottom pt-3 text-left text-xs text-slate-500">TME: tempo médio de espera · TMD: tempo médio de deslocamento · TMA: tempo médio de atendimento. Totais ponderados pelo número de atendimentos.</caption>
</x-report>
