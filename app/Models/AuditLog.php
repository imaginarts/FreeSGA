<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de auditoria (imutável). */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'auth.login' => 'Login',
        'auth.failed' => 'Falha de login',
        'auth.logout' => 'Logout',
        'model.created' => 'Cadastro criado',
        'model.updated' => 'Cadastro alterado',
        'model.deleted' => 'Cadastro removido',
        'ticket.issued' => 'Senha emitida',
        'ticket.called' => 'Senha chamada',
        'ticket.started' => 'Atendimento iniciado',
        'ticket.finished' => 'Atendimento encerrado',
        'ticket.no_show' => 'Não compareceu',
        'ticket.cancelled' => 'Senha cancelada',
        'ticket.reactivated' => 'Senha reativada',
        'ticket.transferred' => 'Senha transferida',
        'ticket.redirected' => 'Senha redirecionada',
        'data.archived' => 'Senhas reiniciadas',
        'data.cleared' => 'Atendimentos apagados',
        'privacy.export' => 'Exportação de dados pessoais',
        'privacy.anonymized' => 'Dados pessoais anonimizados',
        'privacy.viewed' => 'Acesso a dados pessoais',
        'privacy.cleanup' => 'Limpeza por retenção',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}
