<?php

namespace App\Providers;

use App\Http\Middleware\EnsureCurrentUnit;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireModule;
use App\Services\TicketPrinter;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TicketPrinter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('pt_BR');

        // auditoria de acesso
        Event::listen(Login::class, fn (Login $e) => Audit::log('auth.login', "Login de {$e->user->login}", userId: $e->user->id));
        Event::listen(Logout::class, fn (Logout $e) => $e->user && Audit::log('auth.logout', "Logout de {$e->user->login}", userId: $e->user->id));
        Event::listen(Failed::class, fn (Failed $e) => Audit::log('auth.failed', 'Falha de login para "'.Str::limit((string) ($e->credentials['login'] ?? ''), 30).'"', userId: $e->user?->id));
        Paginator::defaultView('pagination::tailwind');

        // Reaplica as regras de acesso nas requisições AJAX dos componentes Livewire
        Livewire::addPersistentMiddleware([
            EnsureCurrentUnit::class,
            RequireModule::class,
            RequireAdmin::class,
        ]);
    }
}
