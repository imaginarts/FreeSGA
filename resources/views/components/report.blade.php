@props(['title', 'unit', 'start' => null, 'end' => null, 'usesPeriod' => false, 'filterUser' => null])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} · {{ $unit->name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 12mm; }
        @media print { .no-print { display: none !important; } body { background: #fff !important; } }
        .report td, .report th { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 12px; }
        .report th { background: #f1f5f9; font-weight: 600; }
        .report tfoot td { font-weight: 600; background: #f8fafc; }
    </style>
</head>
<body class="bg-slate-100 font-sans text-slate-900">
<div class="mx-auto my-6 max-w-5xl bg-white p-8 shadow print:m-0 print:max-w-none print:p-0 print:shadow-none">
    <header class="mb-6 flex items-start justify-between border-b-2 border-slate-900 pb-4">
        <div>
            <h1 class="text-xl font-bold">{{ $title }}</h1>
            <p class="text-sm text-slate-600">{{ $unit->name }}</p>
            @if ($usesPeriod)<p class="text-sm text-slate-600">Período de {{ $start->format('d/m/Y') }} a {{ $end->format('d/m/Y') }}</p>@endif
            @if ($filterUser)<p class="text-sm text-slate-600">Atendente: {{ $filterUser->fullName() }}</p>@endif
        </div>
        <div class="text-right text-xs text-slate-500">
            <p>Emitido em {{ now($unit->timezone())->format('d/m/Y H:i') }}</p>
            <p>por {{ auth()->user()->fullName() }}</p>
            <button onclick="window.print()" class="no-print btn btn-primary btn-sm mt-2">Imprimir</button>
        </div>
    </header>
    <table class="report w-full border-collapse">{{ $slot }}</table>
</div>
</body>
</html>
