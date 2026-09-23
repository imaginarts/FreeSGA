<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PanelDisplayController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\TicketTrackingController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

// Público: painel de chamadas e impressão de senha por hash
Route::get('/painel/{panel:public_id}', [PanelDisplayController::class, 'show'])->name('panel.display');
Route::get('/painel/{panel:public_id}/dados', [PanelDisplayController::class, 'data'])->name('panel.data');
Route::get('/senha/{ticket}/imprimir', [PrintController::class, 'public'])->name('ticket.print.public');
Route::get('/avaliar/{ticket}/{token}', [SurveyController::class, 'show'])->name('survey.show');
Route::post('/avaliar/{ticket}/{token}', [SurveyController::class, 'store'])->middleware('throttle:10,1')->name('survey.store');
Route::livewire('/totem/{kiosk:public_id}', Livewire\KioskScreen::class)->name('kiosk.show');
Route::get('/s/{ticket}/{token}', [TicketTrackingController::class, 'show'])->name('ticket.track');
Route::get('/s/{ticket}/{token}/dados', [TicketTrackingController::class, 'data'])->middleware('throttle:60,1')->name('ticket.track.data');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware(['auth', 'unit'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', HomeController::class)->name('home');
    Route::post('/unidade/{unit}', [HomeController::class, 'switchUnit'])->name('unit.switch');
    Route::livewire('/perfil', Livewire\Profile::class)->name('profile');

    Route::middleware('module:triage')->group(function () {
        Route::livewire('/triagem', Livewire\Triage::class)->name('modules.triage');
        Route::get('/triagem/imprimir/{ticket}', [PrintController::class, 'show'])->name('ticket.print');
    });
    Route::livewire('/atendimento', Livewire\Attendance::class)->middleware('module:attendance')->name('modules.attendance');
    Route::livewire('/monitor', Livewire\Monitor::class)->middleware('module:monitor')->name('modules.monitor');
    Route::livewire('/paineis', Livewire\Panels::class)->middleware('module:panel')->name('modules.panel');
    Route::middleware('module:reports')->group(function () {
        Route::livewire('/relatorios', Livewire\Reports::class)->name('modules.reports');
        Route::get('/relatorios/{report}', [ReportController::class, 'show'])->name('reports.show');
    });
    Route::livewire('/agendamentos', Livewire\Appointments::class)->middleware('module:scheduling')->name('modules.scheduling');
    Route::livewire('/clientes', Livewire\Customers::class)->middleware('module:customers')->name('modules.customers');
    Route::livewire('/usuarios', Livewire\Users::class)->middleware('module:users')->name('modules.users');
    Route::livewire('/configuracoes', Livewire\UnitSettings::class)->middleware('module:settings')->name('modules.settings');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::livewire('/', Livewire\Admin\General::class)->name('index');
        Route::livewire('/unidades', Livewire\Admin\Units::class)->name('units');
        Route::livewire('/servicos', Livewire\Admin\Services::class)->name('services');
        Route::livewire('/prioridades', Livewire\Admin\Priorities::class)->name('priorities');
        Route::livewire('/locais', Livewire\Admin\Locations::class)->name('locations');
        Route::livewire('/departamentos', Livewire\Admin\Departments::class)->name('departments');
        Route::livewire('/perfis', Livewire\Admin\Roles::class)->name('roles');
        Route::livewire('/motivos-pausa', Livewire\Admin\PauseReasons::class)->name('pause-reasons');
        Route::livewire('/webhooks', Livewire\Admin\Webhooks::class)->name('webhooks');
        Route::livewire('/api', Livewire\Admin\ApiTokens::class)->name('api');
        Route::livewire('/mensagens', Livewire\Admin\Messaging::class)->name('messaging');
        Route::livewire('/privacidade', Livewire\Admin\Privacy::class)->name('privacy');
    });
});
