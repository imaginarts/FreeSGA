<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Senha {{ $ticket->code() }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #000; }
        .ticket { width: 80mm; padding: 6mm 4mm; text-align: center; }
        .unit { font-size: 15px; font-weight: bold; text-transform: uppercase; }
        .header, .footer, .message { font-size: 12px; white-space: pre-line; margin: 6px 0; }
        .priority { font-size: 13px; font-weight: bold; text-transform: uppercase; margin-top: 8px; }
        .code { font-size: 56px; font-weight: bold; letter-spacing: 2px; margin: 4px 0; }
        .service { font-size: 15px; font-weight: bold; }
        .date { font-size: 11px; margin-top: 8px; }
        .qr { margin: 8px auto 0; width: 130px; }
        .qr svg { display: block; width: 130px; height: 130px; }
        .qr-text { font-size: 11px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 8px 0; }
    </style>
</head>
<body @if ($autoprint) onload="window.print()" @endif>
<div class="ticket">
    @if ($unit->print_show_unit_name)<div class="unit">{{ $unit->name }}</div>@endif
    @if ($unit->print_header)<div class="header">{{ $unit->print_header }}</div>@endif
    <hr>
    @if ($unit->print_show_priority)<div class="priority">{{ $ticket->priority->name }}</div>@endif
    <div class="code">{{ $ticket->code() }}</div>
    @if ($unit->print_show_service_name)<div class="service">{{ $ticket->service->name }}</div>@endif
    @if ($unit->print_show_service_message && $message)<div class="message">{{ $message }}</div>@endif
    @if ($unit->feature('mobile_ticket'))
        <div class="qr">{!! $ticket->trackingQrSvg(130) !!}</div>
        <div class="qr-text">Acompanhe sua senha pelo celular</div>
    @endif
    <hr>
    @if ($unit->print_show_date)<div class="date">{{ $ticket->localTime($ticket->arrived_at, 'd/m/Y H:i') }}</div>@endif
    @if ($unit->print_footer)<div class="footer">{{ $unit->print_footer }}</div>@endif
</div>
</body>
</html>
