@php($tz = auth()->user()->currentUnit?->timezone() ?? config('app.timezone'))
<x-admin-shell>
    <x-page-header title="Privacidade e auditoria" subtitle="LGPD: exibição de dados pessoais, retenção e registro de ações" />

    <div class="mb-6 flex gap-1 border-b border-slate-200 dark:border-slate-800">
        @foreach (['log' => 'Registro de ações', 'privacy' => 'Privacidade (LGPD)', 'audit' => 'Configurar auditoria'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" @class(['-mb-px border-b-2 px-4 py-2.5 text-sm font-medium', 'border-brand-600 text-brand-700 dark:text-brand-100' => $tab === $key, 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    {{-- ============================================================ Registro --}}
    @if ($tab === 'log')
        <div class="mb-4 flex flex-wrap items-end gap-2">
            <input class="input w-56" placeholder="Buscar na descrição…" wire:model.live.debounce.300ms="search">
            <select class="input w-52" wire:model.live="action">
                <option value="">Todas as ações</option>
                <option value="auth.*">Acessos (login/logout)</option>
                <option value="ticket.*">Senhas</option>
                <option value="model.*">Cadastros e configurações</option>
                <option value="privacy.*">Dados pessoais</option>
                <option value="data.*">Rotinas de dados</option>
                @foreach ($actions as $key => $label)<option value="{{ $key }}">— {{ $label }}</option>@endforeach
            </select>
            <select class="input w-48" wire:model.live="userId">
                <option value="">Todos os usuários</option>
                @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->fullName() }} ({{ $u->login }})</option>@endforeach
            </select>
            <input type="date" class="input w-40" wire:model.live="from" title="De">
            <input type="date" class="input w-40" wire:model.live="to" title="Até">
            <button class="btn btn-secondary ml-auto" wire:click="exportCsv"><x-icon name="external" class="size-4" /> Exportar CSV</button>
        </div>

        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Quando</th><th>Usuário</th><th>Ação</th><th>Descrição</th><th>Unidade</th><th>IP</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr wire:key="log-{{ $log->id }}" class="cursor-pointer" wire:click="openDetail({{ $log->id }})">
                        <td class="text-xs whitespace-nowrap">{{ $log->created_at->setTimezone($tz)->format('d/m/Y H:i:s') }}</td>
                        <td class="text-xs">{{ $log->user?->login ?? '—' }}</td>
                        <td>
                            <span @class(['badge whitespace-nowrap',
                                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => in_array($log->action, ['ticket.cancelled', 'auth.failed', 'data.cleared', 'model.deleted', 'privacy.anonymized']),
                                'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' => str_starts_with($log->action, 'privacy.'),
                                'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => true])>{{ $log->actionLabel() }}</span>
                        </td>
                        <td class="max-w-md truncate">{{ $log->description }} @if ($log->changes)<span class="text-xs text-slate-400">· {{ count($log->changes) }} campo(s)</span>@endif</td>
                        <td class="text-xs">{{ $log->unit?->name ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ $log->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Nenhum registro encontrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 flex items-center justify-between gap-4">
            <p class="text-xs text-slate-500">{{ number_format($total, 0, ',', '.') }} registro(s) no total · mantidos por {{ $audit['retention_days'] }} dias</p>
            {{ $logs->links() }}
        </div>

        <x-modal wire:model="showDetail" title="Detalhes do registro" max-width="max-w-2xl">
            @if ($detail)
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-slate-500">Quando</dt><dd>{{ $detail->created_at->setTimezone($tz)->format('d/m/Y H:i:s') }}</dd></div>
                    <div><dt class="text-slate-500">Ação</dt><dd>{{ $detail->actionLabel() }} <code class="text-xs text-slate-400">{{ $detail->action }}</code></dd></div>
                    <div><dt class="text-slate-500">Usuário</dt><dd>{{ $detail->user ? $detail->user->fullName().' ('.$detail->user->login.')' : '—' }}</dd></div>
                    <div><dt class="text-slate-500">Unidade</dt><dd>{{ $detail->unit?->name ?? '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-slate-500">Descrição</dt><dd>{{ $detail->description }}</dd></div>
                    <div><dt class="text-slate-500">IP</dt><dd class="font-mono">{{ $detail->ip ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Navegador</dt><dd class="truncate text-xs">{{ $detail->user_agent ?? '—' }}</dd></div>
                </dl>
                @if ($detail->changes)
                    <table class="table mt-4">
                        <thead><tr><th>Campo</th><th>Antes</th><th>Depois</th></tr></thead>
                        <tbody>
                        @foreach ($detail->changes as $field => [$old, $new])
                            <tr>
                                <td class="font-mono text-xs">{{ $field }}</td>
                                <td class="font-mono text-xs break-all text-red-700">{{ is_array($old) ? json_encode($old, JSON_UNESCAPED_UNICODE) : var_export($old, true) }}</td>
                                <td class="font-mono text-xs break-all text-emerald-700">{{ is_array($new) ? json_encode($new, JSON_UNESCAPED_UNICODE) : var_export($new, true) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            @endif
        </x-modal>
    @endif

    {{-- ============================================================ Privacidade --}}
    @if ($tab === 'privacy')
        <form wire:submit="savePrivacy" class="space-y-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Exibição de dados pessoais</h2></div>
                <div class="card-body space-y-4">
                    <x-field label="Nome do cliente no painel da TV e na chamada por voz" class="max-w-md">
                        <select class="input" wire:model="privacy.panel_customer_name">
                            <option value="hidden">Não exibir</option>
                            <option value="first">Apenas o primeiro nome</option>
                            <option value="full">Nome completo</option>
                        </select>
                    </x-field>
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="checkbox mt-0.5" wire:model="privacy.mask_document">
                        <span><b>Mascarar CPF nas telas de operação</b><br><span class="text-slate-500">Atendimento, monitor, triagem e listas exibem ***.982.247-**. O cadastro do cliente continua mostrando o documento completo.</span></span></label>
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="checkbox mt-0.5" wire:model="privacy.public_attendant_name">
                        <span><b>Mostrar o nome do atendente nas páginas públicas</b><br><span class="text-slate-500">Pesquisa de satisfação.</span></span></label>
                    <x-field label="Texto de consentimento na coleta do telefone" error="privacy.phone_consent_text" hint="Exibido no totem e como orientação na triagem">
                        <textarea class="input" rows="2" wire:model="privacy.phone_consent_text"></textarea>
                    </x-field>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Retenção de dados</h2></div>
                <div class="card-body space-y-4">
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="checkbox mt-0.5" wire:model.live="privacy.retention_enabled">
                        <span><b>Anonimizar clientes inativos automaticamente</b><br><span class="text-slate-500">Clientes sem senhas nem agendamentos no período têm nome, documento, contatos e endereço apagados. As estatísticas são mantidas. <b class="text-red-600">Irreversível.</b></span></span></label>
                    @if ($privacy['retention_enabled'])
                        <x-field label="Após quantos meses sem atendimento" error="privacy.retention_months" class="max-w-56 pl-7">
                            <input type="number" min="1" max="120" class="input" wire:model="privacy.retention_months">
                        </x-field>
                    @endif
                    <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-800/50 dark:text-slate-400">
                        <p class="font-medium text-slate-700 dark:text-slate-300">Sempre aplicado:</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-4">
                            <li>telefones para avisos são apagados quando as senhas são reiniciadas (fim do dia);</li>
                            <li>o histórico de mensagens enviadas é apagado após 90 dias;</li>
                            <li>o painel da TV nunca exibe o documento do cliente;</li>
                            <li>no módulo Clientes, o titular pode receber seus dados (exportar) ou pedir a eliminação (anonimizar).</li>
                        </ul>
                    </div>
                    <p class="text-xs text-slate-500">A rotina roda diariamente às 03:00 (<code>php artisan sga:privacy-cleanup</code>).</p>
                </div>
            </div>

            <div class="flex justify-between">
                <button type="button" class="btn btn-secondary" wire:click="runCleanup" wire:confirm="Executar agora a limpeza de retenção?">Executar limpeza agora</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    @endif

    {{-- ============================================================ Auditoria --}}
    @if ($tab === 'audit')
        <form wire:submit="saveAudit" class="card">
            <div class="card-header"><h2 class="card-title">O que registrar</h2></div>
            <div class="card-body space-y-4">
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="checkbox mt-0.5" wire:model.live="audit.enabled">
                    <span><b>Habilitar auditoria</b><br><span class="text-slate-500">Sempre registra: ações sensíveis em senhas (cancelar, reativar, transferir, redirecionar, não compareceu), alterações de cadastros e configurações e rotinas de dados.</span></span></label>
                <div @class(['space-y-4 border-l-2 border-slate-200 pl-5 dark:border-slate-700', 'pointer-events-none opacity-50' => ! $audit['enabled']])>
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="checkbox mt-0.5" wire:model="audit.log_ticket_flow">
                        <span><b>Registrar o fluxo completo das senhas</b><br><span class="text-slate-500">Emissão, chamada, início e encerramento. Gera muitos registros.</span></span></label>
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="checkbox mt-0.5" wire:model="audit.log_logins">
                        <span><b>Registrar acessos</b><br><span class="text-slate-500">Login, logout e tentativas com senha errada.</span></span></label>
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="checkbox mt-0.5" wire:model="audit.log_data_access">
                        <span><b>Registrar acesso a dados pessoais</b><br><span class="text-slate-500">Consulta de histórico, exportação e anonimização de clientes.</span></span></label>
                    <x-field label="Manter registros por (dias)" error="audit.retention_days" class="max-w-56">
                        <input type="number" min="30" max="3650" class="input" wire:model="audit.retention_days">
                    </x-field>
                </div>
                <p class="text-xs text-slate-500">Senhas, tokens e cabeçalhos nunca são gravados nos registros. Alterações de clientes registram apenas quais campos mudaram, sem os valores.</p>
            </div>
            <div class="flex justify-end border-t border-slate-200 px-5 py-3 dark:border-slate-800"><button class="btn btn-primary">Salvar</button></div>
        </form>
    @endif
</x-admin-shell>
