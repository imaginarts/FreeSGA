@php($appearance = \App\Models\Setting::get('appearance'))
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>Avalie seu atendimento · {{ $unit->name }}</title>
    @vite(['resources/css/app.css'])
    <style>:root { --color-brand-600: {{ $appearance['primary_color'] }}; }</style>
</head>
<body class="min-h-full bg-slate-100 font-sans text-slate-900 antialiased">
<main class="mx-auto flex min-h-full max-w-md flex-col gap-4 p-4">
    <header class="pt-2 text-center">
        <p class="text-sm font-medium text-slate-500">{{ $unit->name }}</p>
        <p class="text-xs text-slate-400">Senha {{ $ticket->code() }} · {{ $ticket->service->name }}</p>
    </header>

    <section class="rounded-3xl bg-white p-6 shadow-sm">
        @if ($state === 'answered')
            <div class="py-8 text-center">
                <p class="text-5xl">🙏</p>
                <p class="mt-4 text-xl font-semibold">Obrigado pela avaliação!</p>
                <p class="mt-1 text-slate-500">Sua opinião nos ajuda a melhorar.</p>
            </div>
        @elseif ($state === 'pending')
            <p class="py-8 text-center text-slate-600">A avaliação fica disponível assim que seu atendimento for encerrado.</p>
        @elseif ($state === 'expired')
            <p class="py-8 text-center text-slate-600">O prazo para avaliar este atendimento terminou.</p>
        @else
            <form method="POST" action="{{ route('survey.store', [$ticket, $token]) }}" x-data="{ score: @js(old('score')) }">
                @csrf
                <h1 class="text-center text-lg font-semibold">{{ $question }}</h1>
                @if ($ticket->user && \App\Models\Setting::get('privacy')['public_attendant_name'])<p class="mt-1 text-center text-sm text-slate-500">Atendido por {{ $ticket->user->name }}</p>@endif

                <input type="hidden" name="score" :value="score">
                @if ($scale === 'nps')
                    <div class="mt-6 grid grid-cols-6 gap-2">
                        @foreach (range(0, 10) as $n)
                            <button type="button" @click="score = {{ $n }}"
                                    class="aspect-square rounded-xl border-2 text-lg font-bold transition"
                                    :class="score === {{ $n }} ? '{{ $n <= 6 ? 'bg-red-500' : ($n <= 8 ? 'bg-amber-400' : 'bg-emerald-500') }} border-transparent text-white scale-110' : 'border-slate-200 text-slate-700'">{{ $n }}</button>
                        @endforeach
                    </div>
                    <div class="mt-2 flex justify-between text-xs text-slate-400"><span>Nada provável</span><span>Muito provável</span></div>
                @else
                    <div class="mt-6 flex justify-between gap-2">
                        @foreach ([1 => ['😠', 'Péssimo'], 2 => ['🙁', 'Ruim'], 3 => ['😐', 'Regular'], 4 => ['🙂', 'Bom'], 5 => ['😍', 'Ótimo']] as $n => [$emoji, $label])
                            <button type="button" @click="score = {{ $n }}" class="flex flex-1 flex-col items-center gap-1 rounded-2xl border-2 py-3 transition"
                                    :class="score === {{ $n }} ? 'border-brand-600 bg-brand-50 scale-105' : 'border-slate-200'">
                                <span class="text-3xl">{{ $emoji }}</span><span class="text-xs text-slate-600">{{ $label }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
                @error('score')<p class="mt-2 text-center text-sm text-red-600">{{ $message }}</p>@enderror

                @if ($askComment)
                    <label class="mt-6 block text-sm font-medium text-slate-700">Quer deixar um comentário? <span class="text-slate-400">(opcional)</span></label>
                    <textarea name="comment" rows="3" maxlength="1000" class="input mt-1">{{ old('comment') }}</textarea>
                @endif

                <button class="btn btn-primary btn-lg mt-6 w-full" :disabled="score === null">Enviar avaliação</button>
            </form>
        @endif
    </section>
</main>
@livewireScripts {{-- traz o Alpine usado na escolha da nota --}}
</body>
</html>
