<?php

use App\Http\Controllers\Api\TicketApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::get('/', [TicketApiController::class, 'status']);
    Route::get('/me', fn () => request()->user()->only(['id', 'login', 'name', 'last_name', 'email', 'is_admin']));

    Route::get('/units', [TicketApiController::class, 'units']);
    Route::get('/units/{unit}/services', [TicketApiController::class, 'unitServices']);
    Route::get('/units/{unit}/queue', [TicketApiController::class, 'queue']);
    Route::get('/units/{unit}/panel', [TicketApiController::class, 'panel']);
    Route::post('/units/{unit}/call-next', [TicketApiController::class, 'callNext']);
    Route::get('/priorities', [TicketApiController::class, 'priorities']);

    Route::post('/tickets', [TicketApiController::class, 'store']);
    Route::get('/tickets/{ticket}', [TicketApiController::class, 'show']);
    Route::post('/tickets/{ticket}/{action}', [TicketApiController::class, 'action'])
        ->whereIn('action', ['call', 'recall', 'start', 'finish', 'no-show', 'redirect', 'cancel', 'reactivate', 'transfer']);
});
