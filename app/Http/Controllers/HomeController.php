<?php

namespace App\Http\Controllers;

use App\Enums\Module;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $unit = $user->currentUnit;

        $modules = collect(Module::cases())->filter(fn (Module $m) => $unit && $user->canAccessModule($m));

        $stats = null;
        if ($unit) {
            $since = CarbonImmutable::now($unit->timezone())->startOfDay()->utc();
            $today = Ticket::current()->where('unit_id', $unit->id)->where('arrived_at', '>=', $since);

            $stats = [
                'issued' => (clone $today)->count(),
                'waiting' => Ticket::current()->where('unit_id', $unit->id)->where('status', TicketStatus::Issued)->count(),
                'finished' => (clone $today)->where('status', TicketStatus::Finished)->count(),
                'avg_wait' => (int) (clone $today)->whereNotNull('wait_time')->avg('wait_time'),
            ];
        }

        return view('home', compact('modules', 'stats', 'unit'));
    }

    public function switchUnit(Request $request, Unit $unit): RedirectResponse
    {
        abort_unless($request->user()->availableUnits()->contains('id', $unit->id), 403);

        $request->user()->forceFill(['current_unit_id' => $unit->id])->saveQuietly();

        return back()->with('success', "Unidade alterada para {$unit->name}.");
    }
}
