<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Garante que o usuário logado esteja numa unidade válida e a compartilha com as views. */
class EnsureCurrentUnit
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['login' => 'Conta desativada.']);
        }

        if ($user) {
            $units = $user->availableUnits();
            $unit = $units->firstWhere('id', $user->current_unit_id) ?? $units->first();

            if ($unit?->id !== $user->current_unit_id) {
                $user->forceFill(['current_unit_id' => $unit?->id])->saveQuietly();
            }

            $user->setRelation('currentUnit', $unit);
            View::share('currentUnit', $unit);
            View::share('availableUnits', $units);
        }

        return $next($request);
    }
}
