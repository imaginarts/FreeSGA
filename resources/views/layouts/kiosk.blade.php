<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Totem' }}</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
    <style>
        html, body { overscroll-behavior: none; touch-action: manipulation; -webkit-user-select: none; user-select: none; }
        .kiosk-btn { transition: transform .08s ease; }
        .kiosk-btn:active { transform: scale(.97); }
    </style>
</head>
<body class="h-full overflow-hidden bg-slate-100 font-sans text-slate-900 antialiased" oncontextmenu="return false">
    {{ $slot }}
    @livewireScripts
</body>
</html>
