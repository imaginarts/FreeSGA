@php($fmt = fn ($s) => \App\Models\Ticket::formatSeconds($s))
<x-report :title="$title" :unit="$unit" :start="$start" :end="$end" :uses-period="$usesPeriod" :filter-user="$filterUser">
    <thead><tr><th>Atendente</th><th>Motivo</th><th class="text-right">Pausas</th><th>Tempo total</th><th class="text-right">Acima do limite</th></tr></thead>
    <tbody>
    @forelse ($rows as $r)
        @foreach ($r['by_reason'] as $reason => $g)
            <tr>
                <td>{{ $loop->first ? $r['user']?->fullName() : '' }}</td>
                <td>{{ $reason }}</td>
                <td class="text-right">{{ $g['count'] }}</td>
                <td>{{ $fmt($g['total']) }}</td>
                <td></td>
            </tr>
        @endforeach
        <tr class="bg-slate-50">
            <td></td>
            <td class="font-semibold">Subtotal</td>
            <td class="text-right font-semibold">{{ $r['count'] }}</td>
            <td class="font-semibold">{{ $fmt($r['total']) }}</td>
            <td @class(['text-right font-semibold', 'text-red-600' => $r['exceeded'] > 0])>{{ $r['exceeded'] }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="text-center">Nenhuma pausa no período.</td></tr>
    @endforelse
    </tbody>
    @if ($rows->isNotEmpty())
        <tfoot><tr><td colspan="2">Total</td><td class="text-right">{{ $rows->sum('count') }}</td><td>{{ $fmt($rows->sum('total')) }}</td><td class="text-right">{{ $rows->sum('exceeded') }}</td></tr></tfoot>
    @endif
</x-report>
