@php($indexLabel = $rows['scale'] === 'nps' ? 'NPS' : 'Satisfeitos')
@php($fmtIndex = fn ($r) => $r['index'] === null ? '—' : $r['index'].($rows['scale'] === 'nps' ? '' : '%'))
<x-report :title="$title" :unit="$unit" :start="$start" :end="$end" :uses-period="$usesPeriod" :filter-user="$filterUser">
    <thead><tr><th>Atendente / Serviço</th><th class="text-right">Respostas</th><th class="text-right">Média</th><th class="text-right">{{ $rows['scale'] === 'nps' ? 'Promotores' : 'Positivas' }}</th><th class="text-right">{{ $rows['scale'] === 'nps' ? 'Detratores' : 'Negativas' }}</th><th class="text-right">{{ $indexLabel }}</th></tr></thead>
    <tbody>
        <tr><td colspan="6" class="bg-slate-100 font-semibold">Por atendente</td></tr>
        @forelse ($rows['attendants'] as $name => $r)
            <tr><td>{{ $name }}</td><td class="text-right">{{ $r['total'] }}</td><td class="text-right">{{ number_format($r['avg'], 1, ',', '.') }}</td><td class="text-right">{{ $r['positive'] }}</td><td class="text-right">{{ $r['negative'] }}</td><td class="text-right font-semibold">{{ $fmtIndex($r) }}</td></tr>
        @empty
            <tr><td colspan="6" class="text-center">Nenhuma avaliação no período.</td></tr>
        @endforelse
        <tr><td colspan="6" class="bg-slate-100 font-semibold">Por serviço</td></tr>
        @foreach ($rows['services'] as $name => $r)
            <tr><td>{{ $name }}</td><td class="text-right">{{ $r['total'] }}</td><td class="text-right">{{ number_format($r['avg'], 1, ',', '.') }}</td><td class="text-right">{{ $r['positive'] }}</td><td class="text-right">{{ $r['negative'] }}</td><td class="text-right font-semibold">{{ $fmtIndex($r) }}</td></tr>
        @endforeach
    </tbody>
    <tfoot><tr><td>Geral</td><td class="text-right">{{ $rows['overall']['total'] }}</td><td class="text-right">{{ $rows['overall']['total'] ? number_format($rows['overall']['avg'], 1, ',', '.') : '—' }}</td><td class="text-right">{{ $rows['overall']['positive'] }}</td><td class="text-right">{{ $rows['overall']['negative'] }}</td><td class="text-right">{{ $fmtIndex($rows['overall']) }}</td></tr></tfoot>
    <caption class="caption-bottom pt-3 text-left text-xs text-slate-500">
        {{ $rows['scale'] === 'nps' ? 'NPS = % de promotores (9-10) − % de detratores (0-6). Varia de −100 a 100.' : 'Satisfeitos = % de notas 4 e 5.' }}
        @if ($rows['comments']->isNotEmpty())
            <div class="mt-6">
                <p class="mb-2 text-sm font-semibold text-slate-800">Comentários</p>
                @foreach ($rows['comments'] as $c)
                    <div class="border-b border-slate-200 py-2 text-sm text-slate-700">
                        <span class="font-mono font-semibold">{{ $c->score }}</span> ·
                        <span class="text-slate-500">{{ $c->created_at->setTimezone($unit->timezone())->format('d/m H:i') }} · {{ $c->service->name }} · {{ $c->user?->name ?? '—' }}</span>
                        <p class="mt-0.5">{{ $c->comment }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </caption>
</x-report>
