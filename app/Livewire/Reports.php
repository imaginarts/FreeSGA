<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithUnit;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Relatórios')]
class Reports extends Component
{
    use InteractsWithUnit;

    public string $start = '';

    public string $end = '';

    public ?int $userId = null;

    public function mount(): void
    {
        $today = CarbonImmutable::now($this->unit()->timezone());
        $this->start = $today->subDays(6)->toDateString();
        $this->end = $today->toDateString();
    }

    public function updated(): void
    {
        $this->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ], [], ['start' => 'data inicial', 'end' => 'data final']);
    }

    public function getListeners(): array
    {
        return [];
    }

    public function render(ReportService $reports)
    {
        $unit = $this->unit();
        $range = $reports->range($unit, $this->start ?: now()->toDateString(), $this->end ?: now()->toDateString());

        $status = $reports->byStatus($unit, $range, $this->userId);
        $charts = [
            'status' => $status,
            'services' => $reports->byService($unit, $range, $this->userId),
            'averages' => $reports->averages($unit, $range, $this->userId),
        ];

        $this->dispatch('charts-updated', charts: $charts);

        return view('livewire.reports', [
            'charts' => $charts,
            'total' => $status->sum(),
            'users' => User::whereHas('allocations', fn ($q) => $q->where('unit_id', $unit->id))->orderBy('name')->get(),
            'reports' => ReportService::REPORTS,
        ]);
    }
}
