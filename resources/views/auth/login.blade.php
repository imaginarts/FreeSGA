@php($appearance = \App\Models\Setting::get('appearance'))
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · {{ $appearance['app_name'] }}</title>
    <script>if (localStorage.getItem('sga.dark') === '1') document.documentElement.classList.add('dark')</script>
    @vite(['resources/css/app.css'])
</head>
<body class="grid h-full place-items-center bg-gradient-to-br from-slate-100 to-brand-100 px-4 font-sans antialiased dark:from-slate-950 dark:to-brand-900/40">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-brand-600 text-white shadow-lg shadow-brand-600/30"><x-icon name="ticket" class="size-7" /></span>
            <h1 class="mt-4 text-2xl font-semibold text-slate-900 dark:text-white">{{ $appearance['app_name'] }}</h1>
            <p class="text-sm text-slate-500">Sistema de Gerenciamento de Atendimento</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="card card-body space-y-4">
            @csrf
            <x-field label="Usuário" for="login" error="login">
                <input id="login" name="login" value="{{ old('login') }}" class="input" autocomplete="username" autofocus required>
            </x-field>
            <x-field label="Senha" for="password">
                <input id="password" type="password" name="password" class="input" autocomplete="current-password" required>
            </x-field>
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                <input type="checkbox" name="remember" class="checkbox"> Manter conectado
            </label>
            <button class="btn btn-primary w-full">Entrar</button>
        </form>
    </div>
</body>
</html>
