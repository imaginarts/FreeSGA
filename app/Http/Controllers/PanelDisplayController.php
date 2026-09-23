<?php

namespace App\Http\Controllers;

use App\Models\Panel;
use App\Models\PanelCall;
use App\Models\Service;
use App\Services\WaitEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PanelDisplayController extends Controller
{
    public function show(Panel $panel): View
    {
        $panel->load('unit');

        return view('panel', ['panel' => $panel, 'unit' => $panel->unit]);
    }

    /** Últimas chamadas dos serviços do painel. */
    public function data(Panel $panel, WaitEstimator $estimator): JsonResponse
    {
        $calls = PanelCall::with('service')
            ->where('unit_id', $panel->unit_id)
            ->whereIn('service_id', $panel->services()->pluck('services.id'))
            ->latest('id')
            ->limit(10)
            ->get()
            ->map->toPanelArray();

        $estimates = [];
        if ($panel->setting('show_eta')) {
            $services = $panel->services()->orderBy('name')->get(['services.id', 'services.name']);
            $values = $estimator->forServices($panel->unit, $services->pluck('id')->all());
            $estimates = $services->map(fn (Service $s) => [
                'service' => $s->name,
                'waiting' => $values[$s->id]['waiting'],
                'eta' => $values[$s->id]['waiting'] === 0 ? 'Sem fila' : WaitEstimator::format($values[$s->id]['eta']),
            ])->values();
        }

        return response()->json([
            'calls' => $calls,
            'estimates' => $estimates,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
