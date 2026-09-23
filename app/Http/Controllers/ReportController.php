<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function show(Request $request, string $report, ReportService $reports): View
    {
        abort_unless(array_key_exists($report, ReportService::REPORTS), 404);

        $unit = $request->user()->currentUnit;
        $today = CarbonImmutable::now($unit->timezone())->toDateString();
        $data = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date'],
            'user' => ['nullable', 'integer'],
        ]);
        $start = $data['start'] ?? $today;
        $end = $data['end'] ?? $today;
        $userId = $data['user'] ?? null;

        [$title, $usesPeriod] = ReportService::REPORTS[$report];

        return view('reports.'.$report, [
            'title' => $title,
            'usesPeriod' => $usesPeriod,
            'unit' => $unit,
            'start' => CarbonImmutable::parse($start),
            'end' => CarbonImmutable::parse($end),
            'filterUser' => $userId ? User::find($userId) : null,
            'rows' => $reports->build($report, $unit, $reports->range($unit, $start, $end), $userId),
        ]);
    }
}
