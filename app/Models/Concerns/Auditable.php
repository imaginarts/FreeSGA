<?php

namespace App\Models\Concerns;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra criação, alteração e remoção do model na auditoria.
 * O model pode definir:
 *  - $auditLabel: nome amigável ("Serviço")
 *  - $auditExclude: campos ignorados
 *  - $auditValues = false: registra só os nomes dos campos alterados (dados pessoais)
 */
trait Auditable
{
    /** Campos técnicos que mudam sozinhos e não interessam à auditoria. */
    private static array $auditIgnored = [
        'created_at', 'updated_at', 'deleted_at', 'remember_token', 'last_login_at', 'last_login_ip',
        'current_unit_id', 'priority_swap_count', 'next_number', 'last_used_at', 'last_error', 'last_seen_at',
    ];

    /** Nunca gravar o valor destes campos. */
    private static array $auditSecret = ['password', 'token', 'secret', 'headers', 'whatsapp_token', 'http_headers'];

    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->audit('model.created', 'criado'));
        static::updated(fn (Model $model) => $model->audit('model.updated', 'alterado'));
        static::deleted(fn (Model $model) => $model->audit('model.deleted', 'removido'));
    }

    protected function audit(string $action, string $verb): void
    {
        $changes = $action === 'model.updated' ? $this->auditChanges() : null;

        if ($action === 'model.updated' && ! $changes) {
            return;
        }

        $name = $this->getAttribute('name') ?? $this->getAttribute('key') ?? $this->getAttribute('login');
        $label = property_exists($this, 'auditLabel') ? $this->auditLabel : class_basename($this);

        Audit::log($action, trim("{$label} #{$this->getKey()} ".($name ? "({$name}) " : '').$verb), $this, $changes);
    }

    private function auditChanges(): array
    {
        $exclude = array_merge(self::$auditIgnored, property_exists($this, 'auditExclude') ? $this->auditExclude : []);
        $withValues = ! property_exists($this, 'auditValues') || $this->auditValues;
        $changes = [];

        foreach ($this->getChanges() as $field => $new) {
            if (in_array($field, $exclude, true)) {
                continue;
            }

            if (! $withValues || $this->isSecret($field)) {
                $changes[$field] = ['alterado', 'alterado'];

                continue;
            }

            $changes[$field] = [$this->scrub($field, $this->getOriginal($field)), $this->scrub($field, $new)];
        }

        return $changes;
    }

    private function isSecret(string $field): bool
    {
        foreach (self::$auditSecret as $secret) {
            if (str_contains(strtolower($field), $secret)) {
                return true;
            }
        }

        return false;
    }

    /** Converte para valor legível e remove segredos de estruturas JSON (ex.: configurações). */
    private function scrub(string $field, mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_string($value) && str_starts_with(ltrim($value), '{')) {
            $value = json_decode($value, true) ?? $value;
        }
        if (is_array($value)) {
            array_walk_recursive($value, function (&$v, $k) {
                if (is_string($k) && $this->isSecret($k) && $v !== '' && $v !== null) {
                    $v = '••••';
                }
            });
        }

        return $value;
    }
}
