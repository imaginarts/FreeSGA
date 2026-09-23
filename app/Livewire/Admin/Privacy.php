<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\PrivacyService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Privacidade e auditoria')]
class Privacy extends Component
{
    use WithPagination;

    public array $privacy = [];

    public array $audit = [];

    #[Url]
    public string $tab = 'log';

    // filtros da consulta
    #[Url]
    public string $search = '';

    #[Url]
    public string $action = '';

    #[Url]
    public ?int $userId = null;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public ?int $detailId = null;

    public bool $showDetail = false;

    public function mount(): void
    {
        $this->privacy = Setting::get('privacy');
        $this->audit = Setting::get('audit');
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'action', 'userId', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function savePrivacy(): void
    {
        $this->validate([
            'privacy.panel_customer_name' => ['required', 'in:hidden,first,full'],
            'privacy.mask_document' => ['boolean'],
            'privacy.public_attendant_name' => ['boolean'],
            'privacy.phone_consent_text' => ['required', 'string', 'max:300'],
            'privacy.retention_enabled' => ['boolean'],
            'privacy.retention_months' => ['required', 'integer', 'min:1', 'max:120'],
        ], [], ['privacy.phone_consent_text' => 'texto de consentimento', 'privacy.retention_months' => 'meses']);

        Setting::put('privacy', $this->privacy);
        $this->dispatch('toast', type: 'success', message: 'Configurações de privacidade salvas.');
    }

    public function saveAudit(): void
    {
        $this->validate([
            'audit.enabled' => ['boolean'],
            'audit.log_ticket_flow' => ['boolean'],
            'audit.log_logins' => ['boolean'],
            'audit.log_data_access' => ['boolean'],
            'audit.retention_days' => ['required', 'integer', 'min:30', 'max:3650'],
        ], [], ['audit.retention_days' => 'dias de retenção']);

        Setting::put('audit', $this->audit);
        $this->dispatch('toast', type: 'success', message: 'Configurações de auditoria salvas.');
    }

    public function runCleanup(PrivacyService $privacy): void
    {
        $r = $privacy->cleanup();
        $this->dispatch('toast', type: 'success', message: "Limpeza concluída: {$r['customers']} cliente(s) anonimizado(s), {$r['audit']} registro(s) de auditoria apagados.");
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $this->showDetail = true;
    }

    /** Exporta os registros filtrados em CSV (separador ";" para abrir no Excel). */
    public function exportCsv()
    {
        $rows = $this->query()->with(['user', 'unit'])->limit(50000)->get();
        $tz = auth()->user()->currentUnit?->timezone() ?? config('app.timezone');

        return response()->streamDownload(function () use ($rows, $tz) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Data', 'Usuário', 'Unidade', 'Ação', 'Descrição', 'Alterações', 'IP'], ';');
            foreach ($rows as $log) {
                fputcsv($out, [
                    $log->created_at->setTimezone($tz)->format('d/m/Y H:i:s'),
                    $log->user?->login ?? '—',
                    $log->unit?->name ?? '—',
                    $log->actionLabel(),
                    $log->description,
                    $log->changes ? json_encode($log->changes, JSON_UNESCAPED_UNICODE) : '',
                    $log->ip,
                ], ';');
            }
            fclose($out);
        }, 'auditoria-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(): Builder
    {
        return AuditLog::query()
            ->when($this->search, fn ($q) => $q->where('description', 'like', "%{$this->search}%"))
            ->when($this->action, fn ($q) => str_ends_with($this->action, '.*')
                ? $q->where('action', 'like', substr($this->action, 0, -1).'%')
                : $q->where('action', $this->action))
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->when($this->from, fn ($q) => $q->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to, fn ($q) => $q->where('created_at', '<=', $this->to.' 23:59:59'))
            ->latest('id');
    }

    public function render()
    {
        return view('livewire.admin.privacy', [
            'logs' => $this->query()->with(['user', 'unit'])->paginate(30),
            'actions' => AuditLog::ACTIONS,
            'users' => User::withTrashed()->orderBy('name')->get(['id', 'name', 'last_name', 'login']),
            'detail' => $this->detailId ? AuditLog::with(['user', 'unit'])->find($this->detailId) : null,
            'total' => AuditLog::count(),
        ]);
    }
}
