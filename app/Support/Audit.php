<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/** Grava registros de auditoria respeitando as opções em Admin → Privacidade e auditoria. */
class Audit
{
    /** Ações de senha sempre registradas (as demais só com "fluxo completo"). */
    public const SENSITIVE_TICKET_ACTIONS = [
        'ticket.no_show', 'ticket.cancelled', 'ticket.reactivated', 'ticket.transferred', 'ticket.redirected',
    ];

    public static function enabledFor(string $action): bool
    {
        $config = Setting::get('audit');

        if (! $config['enabled']) {
            return false;
        }

        return match (true) {
            str_starts_with($action, 'auth.') => (bool) $config['log_logins'],
            in_array($action, ['privacy.viewed', 'privacy.export', 'privacy.anonymized'], true) => (bool) $config['log_data_access'],
            str_starts_with($action, 'ticket.') => $config['log_ticket_flow'] || in_array($action, self::SENSITIVE_TICKET_ACTIONS, true),
            default => true,
        };
    }

    public static function log(string $action, string $description, ?Model $subject = null, ?array $changes = null, ?int $unitId = null, ?int $userId = null): void
    {
        if (! self::enabledFor($action)) {
            return;
        }

        try {
            $user = auth()->user();
            $request = app()->runningInConsole() ? null : request();

            AuditLog::create([
                'user_id' => $userId ?? $user?->id,
                'unit_id' => $unitId ?? $subject?->getAttribute('unit_id') ?? $user?->current_unit_id,
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'description' => Str::limit($description, 250),
                'changes' => $changes ?: null,
                'ip' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 190) : 'console',
            ]);
        } catch (Throwable $e) {
            // auditoria nunca pode derrubar a operação principal
            Log::warning('Falha ao gravar auditoria: '.$e->getMessage());
        }
    }
}
