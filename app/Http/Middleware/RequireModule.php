<?php

namespace App\Http\Middleware;

use App\Enums\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireModule
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (! $user->currentUnit) {
            return redirect()->route('home')->with('error', 'Você não está lotado em nenhuma unidade.');
        }

        abort_unless($user->canAccessModule(Module::from($module)), 403, 'Seu perfil não tem acesso a este módulo.');

        return $next($request);
    }
}
