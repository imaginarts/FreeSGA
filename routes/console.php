<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Unit;
use App\Services\PauseService;
use App\Services\PrivacyService;
use App\Services\TicketService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('sga:reset {unit? : ID da unidade (todas se omitido)} {--keep-today : Mantém as senhas emitidas hoje}', function (TicketService $tickets, PauseService $pauses) {
    $unit = $this->argument('unit') ? Unit::findOrFail($this->argument('unit')) : null;
    $count = $tickets->archive($unit, (bool) $this->option('keep-today'));
    $this->info("{$count} senha(s) arquivada(s).");
    $closed = $pauses->closeStale(now()->subHours(12), $unit);
    $this->info("{$closed} pausa(s) esquecida(s) encerrada(s).");
})->purpose('Encerra o período: arquiva senhas, limpa painéis e reinicia contadores');

Artisan::command('sga:appointments-expire', function () {
    $total = 0;
    foreach (Unit::where('active', true)->get() as $unit) {
        $total += Appointment::where('unit_id', $unit->id)
            ->where('status', AppointmentStatus::Scheduled)
            ->whereDate('date', '<', CarbonImmutable::now($unit->timezone())->toDateString())
            ->update(['status' => AppointmentStatus::NoShow]);
    }
    $this->info("{$total} agendamento(s) marcados como não compareceu.");
})->purpose('Marca como "não compareceu" os agendamentos de dias anteriores');

Artisan::command('sga:privacy-cleanup', function (PrivacyService $privacy) {
    $r = $privacy->cleanup();
    $this->info("Clientes anonimizados: {$r['customers']} · CPFs removidos de senhas: {$r['tickets']} · Mensagens apagadas: {$r['notifications']} · Auditoria apagada: {$r['audit']}");
})->purpose('Aplica a política de retenção de dados pessoais (LGPD)');

// Rotina diária (requer "php artisan schedule:work" ou cron com schedule:run)
Schedule::command('sga:privacy-cleanup')->dailyAt('03:00')->timezone(env('SCHEDULE_TIMEZONE', 'America/Sao_Paulo'));
Schedule::command('sga:reset')->dailyAt('00:01')->timezone(env('SCHEDULE_TIMEZONE', 'America/Sao_Paulo'));
Schedule::command('sga:appointments-expire')->dailyAt('00:05')->timezone(env('SCHEDULE_TIMEZONE', 'America/Sao_Paulo'));
