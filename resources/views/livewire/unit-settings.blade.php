<div>
    <x-page-header title="Configurações da unidade" :subtitle="auth()->user()->currentUnit->name" />

    <div class="mb-6 flex gap-1 border-b border-slate-200 dark:border-slate-800">
        @foreach (['services' => 'Serviços', 'triage' => 'Impressão e contadores', 'mobile' => 'Senha no celular', 'sla' => 'Metas e pausas', 'devices' => 'Impressoras e totens', 'notify' => 'Avisos e pesquisa', 'attendants' => 'Atendentes'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" @class(['-mb-px border-b-2 px-4 py-2.5 text-sm font-medium', 'border-brand-600 text-brand-700 dark:text-brand-100' => $tab === $key, 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    {{-- ============================================================ Serviços --}}
    @if ($tab === 'services')
        <div class="mb-4 flex justify-end">
            <button class="btn btn-primary" wire:click="$set('showAdd', true)"><x-icon name="plus" class="size-4" /> Adicionar serviços</button>
        </div>
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Ativo</th><th>Sigla</th><th>Serviço</th><th>Tipo</th><th>Próxima senha</th><th>Departamento</th><th></th></tr></thead>
                <tbody>
                @forelse ($this->unitServices as $us)
                    <tr wire:key="us-{{ $us->id }}">
                        <td>
                            <button wire:click="toggleService({{ $us->id }})" @class(['relative inline-flex h-5 w-9 items-center rounded-full transition', 'bg-brand-600' => $us->active, 'bg-slate-300 dark:bg-slate-700' => ! $us->active]) title="Ativar/desativar">
                                <span @class(['inline-block size-4 rounded-full bg-white shadow transition', 'translate-x-4.5' => $us->active, 'translate-x-0.5' => ! $us->active])></span>
                            </button>
                        </td>
                        <td class="font-mono font-bold">{{ $us->prefix }}</td>
                        <td class="font-medium">{{ $us->service->name }}</td>
                        <td class="text-xs">{{ $us->type->label() }}</td>
                        <td class="font-mono">{{ $us->prefix }}{{ str_pad($us->next_number, 3, '0', STR_PAD_LEFT) }}</td>
                        <td class="text-xs">{{ $us->department?->name ?? '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <button class="btn btn-ghost btn-sm" wire:click="resetCounter({{ $us->id }})" wire:confirm="Reiniciar o contador de {{ $us->service->name }}?" title="Reiniciar contador"><x-icon name="refresh" class="size-4" /></button>
                            <button class="btn btn-ghost btn-sm" wire:click="editService({{ $us->id }})"><x-icon name="pencil" class="size-4" /></button>
                            <button class="btn btn-ghost btn-sm text-red-600" wire:click="removeService({{ $us->id }})" wire:confirm="Remover o serviço da unidade?" @disabled($us->active)><x-icon name="trash" class="size-4" /></button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-slate-500">Nenhum serviço adicionado a esta unidade.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <x-modal wire:model="showAdd" title="Adicionar serviços à unidade">
            <form wire:submit="addServices" class="space-y-4">
                <div class="max-h-72 space-y-1.5 overflow-y-auto">
                    @forelse ($this->availableServices as $s)
                        <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"><input type="checkbox" class="checkbox" value="{{ $s->id }}" wire:model="toAdd"> {{ $s->name }}</label>
                    @empty
                        <p class="text-sm text-slate-500">Todos os serviços do catálogo já estão na unidade.</p>
                    @endforelse
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                    <button class="btn btn-primary">Adicionar</button>
                </div>
            </form>
        </x-modal>

        <x-modal wire:model="showService" title="Configurar serviço na unidade" max-width="max-w-2xl">
            <form wire:submit="saveService" class="space-y-4">
                <p class="text-sm font-semibold text-slate-500 uppercase">Triagem</p>
                <div class="grid gap-3 sm:grid-cols-4">
                    <x-field label="Sigla" error="serviceForm.prefix"><input class="input font-mono uppercase" maxlength="3" wire:model="serviceForm.prefix"></x-field>
                    <x-field label="Peso" error="serviceForm.weight"><input type="number" class="input" wire:model="serviceForm.weight"></x-field>
                    <x-field label="Tipo de senha" class="sm:col-span-2">
                        <select class="input" wire:model="serviceForm.type">
                            @foreach ($serviceTypes as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
                        </select>
                    </x-field>
                    <x-field label="Departamento" class="sm:col-span-2">
                        <select class="input" wire:model="serviceForm.department_id">
                            <option value="">Nenhum</option>
                            @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                        </select>
                    </x-field>
                    <x-field label="Mensagem impressa na senha" error="serviceForm.message" class="sm:col-span-2"><input class="input" wire:model="serviceForm.message"></x-field>
                </div>
                <p class="text-sm font-semibold text-slate-500 uppercase">Contador</p>
                <div class="grid gap-3 sm:grid-cols-4">
                    <x-field label="Número inicial" error="serviceForm.start_number"><input type="number" class="input" wire:model="serviceForm.start_number"></x-field>
                    <x-field label="Número final" error="serviceForm.end_number" hint="Volta ao início"><input type="number" class="input" wire:model="serviceForm.end_number"></x-field>
                    <x-field label="Incremento" error="serviceForm.increment"><input type="number" class="input" wire:model="serviceForm.increment"></x-field>
                    <x-field label="Máximo de senhas" error="serviceForm.max_tickets" hint="Por período"><input type="number" class="input" wire:model="serviceForm.max_tickets"></x-field>
                </div>
                <p class="text-sm font-semibold text-slate-500 uppercase">Meta</p>
                <x-field label="Meta de espera (minutos)" error="serviceForm.wait_target" :hint="'Vazio usa a meta padrão da unidade ('.auth()->user()->currentUnit->setting('sla_default_target').' min)'" class="max-w-xs">
                    <input type="number" min="1" class="input" wire:model="serviceForm.wait_target">
                </x-field>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="serviceForm.active"> Serviço ativo na unidade</label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                    <button class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </x-modal>
    @endif

    {{-- ============================================================ Triagem --}}
    @if ($tab === 'triage')
        <div class="grid gap-6 lg:grid-cols-2">
            <form wire:submit="savePrint" class="card">
                <div class="card-header"><h2 class="card-title">Impressão da senha</h2></div>
                <div class="card-body space-y-4">
                    <x-field label="Cabeçalho" error="print.print_header"><textarea class="input" rows="2" wire:model="print.print_header"></textarea></x-field>
                    <x-field label="Rodapé" error="print.print_footer"><textarea class="input" rows="2" wire:model="print.print_footer"></textarea></x-field>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach (['print_show_unit_name' => 'Nome da unidade', 'print_show_priority' => 'Prioridade', 'print_show_service_name' => 'Nome do serviço', 'print_show_service_message' => 'Mensagem do serviço', 'print_show_date' => 'Data e hora'] as $key => $label)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="print.{{ $key }}"> {{ $label }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="flex justify-end border-t border-slate-200 px-5 py-3 dark:border-slate-800"><button class="btn btn-primary">Salvar</button></div>
            </form>

            <div class="space-y-6">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Contadores</h2></div>
                    <table class="table">
                        <thead><tr><th>Serviço</th><th>Próxima senha</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($this->unitServices->where('active', true) as $us)
                            <tr wire:key="c-{{ $us->id }}">
                                <td>{{ $us->service->name }}</td>
                                <td class="font-mono">{{ $us->prefix }}{{ str_pad($us->next_number, 3, '0', STR_PAD_LEFT) }}</td>
                                <td class="text-right"><button class="btn btn-ghost btn-sm" wire:click="resetCounter({{ $us->id }})" wire:confirm="Reiniciar contador?"><x-icon name="refresh" class="size-4" /></button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="card border-red-200 dark:border-red-900">
                    <div class="card-header"><h2 class="card-title text-red-700 dark:text-red-400">Zona de perigo</h2></div>
                    <div class="card-body space-y-4">
                        <div class="flex items-center justify-between gap-4">
                            <p class="text-sm text-slate-600 dark:text-slate-400"><b>Reiniciar senhas</b>: arquiva as senhas atuais (continuam nos relatórios), limpa o painel e reinicia os contadores.</p>
                            <button class="btn btn-warning shrink-0" wire:click="archive" wire:confirm="Reiniciar as senhas desta unidade?">Reiniciar</button>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <p class="text-sm text-slate-600 dark:text-slate-400"><b>Apagar atendimentos</b>: remove definitivamente todo o histórico da unidade.</p>
                            <button class="btn btn-danger shrink-0" wire:click="clear" wire:confirm="ATENÇÃO: apagar definitivamente todos os atendimentos desta unidade?">Apagar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================ Senha no celular --}}
    @if ($tab === 'mobile')
        <form wire:submit="saveFeatures" class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Acompanhamento pelo celular</h2></div>
                <div class="card-body space-y-4">
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" class="checkbox mt-0.5" wire:model.live="features.mobile_ticket">
                        <span><b>Habilitar senha no celular</b><br><span class="text-slate-500">Imprime um QR code na senha. O cliente abre no celular e acompanha a fila em tempo real, sem instalar nada.</span></span>
                    </label>
                    <div @class(['space-y-4 border-l-2 border-slate-200 pl-5 dark:border-slate-700', 'pointer-events-none opacity-50' => ! $features['mobile_ticket']])>
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" class="checkbox mt-0.5" wire:model="features.mobile_show_position">
                            <span><b>Mostrar posição na fila</b><br><span class="text-slate-500">"Você é o 4º da fila".</span></span>
                        </label>
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" class="checkbox mt-0.5" wire:model="features.mobile_show_eta">
                            <span><b>Mostrar tempo estimado de espera</b></span>
                        </label>
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" class="checkbox mt-0.5" wire:model.live="features.mobile_near_alert">
                            <span><b>Avisar quando estiver chegando a vez</b><br><span class="text-slate-500">Som, vibração e notificação no celular.</span></span>
                        </label>
                        @if ($features['mobile_near_alert'])
                            <x-field label="Avisar quando faltarem (senhas)" error="features.mobile_near_threshold" class="max-w-56 pl-7">
                                <input type="number" min="1" max="20" class="input" wire:model="features.mobile_near_threshold">
                            </x-field>
                        @endif
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" class="checkbox mt-0.5" wire:model="features.triage_show_qr">
                            <span><b>Mostrar QR code na tela da triagem</b><br><span class="text-slate-500">O cliente pode fotografar o código mesmo sem senha impressa.</span></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Tempo estimado de espera</h2></div>
                    <div class="card-body space-y-4">
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" class="checkbox mt-0.5" wire:model="features.triage_show_eta">
                            <span><b>Exibir na triagem</b><br><span class="text-slate-500">Mostra a espera prevista em cada serviço antes de emitir a senha.</span></span>
                        </label>
                        <x-field label="Janela de cálculo (minutos)" error="features.eta_window" hint="Usa o ritmo de chamadas deste período. Sem chamadas recentes, usa a duração média dos atendimentos dos últimos 7 dias." class="max-w-sm">
                            <input type="number" min="15" max="480" class="input" wire:model="features.eta_window">
                        </x-field>
                        <p class="text-xs text-slate-500">No painel da TV, a exibição é ligada em cada painel (módulo Painéis).</p>
                    </div>
                </div>
                <div class="flex justify-end"><button class="btn btn-primary">Salvar configurações</button></div>
            </div>
        </form>
    @endif

    {{-- ============================================================ Metas e pausas --}}
    @if ($tab === 'sla')
        <form wire:submit="saveFeatures" class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Metas de tempo de espera</h2></div>
                <div class="card-body space-y-4">
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" class="checkbox mt-0.5" wire:model.live="features.sla_enabled">
                        <span><b>Habilitar metas de espera</b><br><span class="text-slate-500">Cada serviço pode ter meta própria (em Serviços → editar); os demais usam a meta padrão.</span></span>
                    </label>
                    <div @class(['space-y-4 border-l-2 border-slate-200 pl-5 dark:border-slate-700', 'pointer-events-none opacity-50' => ! $features['sla_enabled']])>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Meta padrão (minutos)" error="features.sla_default_target">
                                <input type="number" min="1" class="input" wire:model="features.sla_default_target">
                            </x-field>
                            <x-field label="Alerta amarelo a partir de (%)" error="features.sla_warning_percent" hint="Percentual da meta já consumido">
                                <input type="number" min="10" max="100" class="input" wire:model="features.sla_warning_percent">
                            </x-field>
                        </div>
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" class="checkbox mt-0.5" wire:model="features.sla_monitor_sound">
                            <span><b>Alerta sonoro no monitor</b><br><span class="text-slate-500">Toca quando uma senha passa a ficar acima da meta.</span></span>
                        </label>
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" class="checkbox mt-0.5" wire:model="features.sla_attendance_highlight">
                            <span><b>Destacar na fila do atendente</b><br><span class="text-slate-500">Senhas próximas ou acima da meta aparecem em amarelo/vermelho.</span></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Pausas do atendente</h2></div>
                    <div class="card-body space-y-4">
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" class="checkbox mt-0.5" wire:model.live="features.pauses_enabled">
                            <span><b>Habilitar pausas</b><br><span class="text-slate-500">O atendente registra pausas (almoço, intervalo…) e não recebe senhas enquanto pausado.</span></span>
                        </label>
                        <div @class(['space-y-4 border-l-2 border-slate-200 pl-5 dark:border-slate-700', 'pointer-events-none opacity-50' => ! $features['pauses_enabled']])>
                            <label class="flex items-start gap-3 text-sm">
                                <input type="checkbox" class="checkbox mt-0.5" wire:model="features.pause_require_reason">
                                <span><b>Exigir motivo da pausa</b><br><span class="text-slate-500">Motivos cadastrados em Administração → Motivos de pausa.</span></span>
                            </label>
                            <label class="flex items-start gap-3 text-sm">
                                <input type="checkbox" class="checkbox mt-0.5" wire:model="features.pause_alert_exceeded">
                                <span><b>Destacar pausa acima do tempo máximo</b><br><span class="text-slate-500">No monitor e no cronômetro do atendente.</span></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end"><button class="btn btn-primary">Salvar configurações</button></div>
            </div>
        </form>
    @endif

    {{-- ============================================================ Impressoras e totens --}}
    @if ($tab === 'devices')
        <form wire:submit="saveFeatures" class="card mb-6">
            <div class="card-body flex flex-wrap items-center gap-x-8 gap-y-3">
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" class="checkbox mt-0.5" wire:model="features.direct_print_enabled">
                    <span><b>Impressão direta em impressora térmica</b><br><span class="text-slate-500">Desligado: a triagem sempre usa a impressão do navegador.</span></span>
                </label>
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" class="checkbox mt-0.5" wire:model="features.kiosk_enabled">
                    <span><b>Totens de autoatendimento</b><br><span class="text-slate-500">Desligado: os links dos totens deixam de funcionar.</span></span>
                </label>
                <button class="btn btn-primary btn-sm ml-auto">Salvar</button>
            </div>
        </form>
        <livewire:devices />
    @endif

    {{-- ============================================================ Avisos e pesquisa --}}
    @if ($tab === 'notify')
        @php($messagingReady = app(\App\Services\MessagingService::class)->configured())
        @unless ($messagingReady)
            <div class="mb-6 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                <x-icon name="alert" class="size-4 shrink-0" />
                Nenhum provedor de WhatsApp/SMS configurado. {{ auth()->user()->is_admin ? 'Configure em Administração → WhatsApp / SMS.' : 'Peça a um administrador para configurar.' }}
                A pesquisa continua disponível na página de acompanhamento.
            </div>
        @endunless
        <form wire:submit="saveFeatures" class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Avisos por WhatsApp/SMS</h2></div>
                <div class="card-body space-y-4">
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" class="checkbox mt-0.5" wire:model.live="features.notify_enabled">
                        <span><b>Habilitar avisos</b><br><span class="text-slate-500">Enviados só para quem informar o número na triagem, no totem ou pela API.</span></span>
                    </label>
                    <div @class(['space-y-4 border-l-2 border-slate-200 pl-5 dark:border-slate-700', 'pointer-events-none opacity-50' => ! $features['notify_enabled']])>
                        <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="checkbox mt-0.5" wire:model="features.notify_on_issue">
                            <span><b>Confirmação da senha</b><br><span class="text-slate-500">Com o link para acompanhar a fila.</span></span></label>
                        <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="checkbox mt-0.5" wire:model.live="features.notify_near">
                            <span><b>Aviso de "sua vez está chegando"</b></span></label>
                        @if ($features['notify_near'])
                            <x-field label="Quando faltarem (senhas)" error="features.notify_near_threshold" class="max-w-56 pl-7">
                                <input type="number" min="1" max="20" class="input" wire:model="features.notify_near_threshold">
                            </x-field>
                        @endif
                        <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="checkbox mt-0.5" wire:model="features.notify_on_call">
                            <span><b>Aviso de senha chamada</b><br><span class="text-slate-500">"Dirija-se ao Guichê 03".</span></span></label>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Pesquisa de satisfação</h2></div>
                    <div class="card-body space-y-4">
                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" class="checkbox mt-0.5" wire:model.live="features.survey_enabled">
                            <span><b>Habilitar pesquisa</b><br><span class="text-slate-500">Uma avaliação por atendimento encerrado, válida por 7 dias.</span></span>
                        </label>
                        <div @class(['space-y-4 border-l-2 border-slate-200 pl-5 dark:border-slate-700', 'pointer-events-none opacity-50' => ! $features['survey_enabled']])>
                            <x-field label="Escala">
                                <select class="input" wire:model="features.survey_scale">
                                    <option value="nps">NPS: nota de 0 a 10</option>
                                    <option value="csat">Satisfação: 1 a 5 (carinhas)</option>
                                </select>
                            </x-field>
                            <x-field label="Pergunta" error="features.survey_question"><input class="input" wire:model="features.survey_question"></x-field>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="features.survey_comment"> Permitir comentário</label>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="features.survey_via_message"> Enviar link por WhatsApp/SMS ao encerrar</label>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="features.survey_on_tracking"> Mostrar na página de acompanhamento</label>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end"><button class="btn btn-primary">Salvar configurações</button></div>
            </div>
        </form>
    @endif

    {{-- ============================================================ Atendentes --}}
    @if ($tab === 'attendants')
        <div class="grid gap-6 lg:grid-cols-[280px_1fr]">
            <div class="card h-fit">
                <div class="card-header"><h2 class="card-title">Atendentes lotados</h2></div>
                <ul class="max-h-[60vh] divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                    @forelse ($this->attendants as $u)
                        <li><button wire:click="$set('attendantId', {{ $u->id }})" @class(['w-full px-4 py-2.5 text-left text-sm', 'bg-brand-50 font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-100' => $attendantId === $u->id, 'hover:bg-slate-50 dark:hover:bg-slate-800' => $attendantId !== $u->id])>{{ $u->fullName() }} <span class="text-xs text-slate-400">{{ $u->login }}</span></button></li>
                    @empty
                        <li class="p-4 text-sm text-slate-500">Nenhum usuário lotado nesta unidade.</li>
                    @endforelse
                </ul>
            </div>

            @if ($attendantId && $attendant)
                <div class="space-y-6">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Serviços atendidos</h2>
                            <div class="flex gap-2">
                                <select class="input !py-1.5" wire:model="addServiceId">
                                    <option value="">Adicionar serviço…</option>
                                    @foreach ($this->unitServices->whereNotIn('service_id', $this->attendantServices->pluck('service_id')) as $us)
                                        <option value="{{ $us->service_id }}">{{ $us->prefix }} - {{ $us->service->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-secondary btn-sm" wire:click="addAttendantService"><x-icon name="plus" class="size-4" /></button>
                                <button class="btn btn-ghost btn-sm" wire:click="addAllAttendantServices" title="Adicionar todos os ativos">Todos</button>
                            </div>
                        </div>
                        <table class="table">
                            <thead><tr><th>Serviço</th><th class="w-32">Peso</th><th></th></tr></thead>
                            <tbody>
                            @forelse ($this->attendantServices as $su)
                                <tr wire:key="su-{{ $su->id }}">
                                    <td>{{ $su->service->name }}</td>
                                    <td><input type="number" min="1" class="input !py-1" value="{{ $su->weight }}" wire:change="updateAttendantServiceWeight({{ $su->id }}, $event.target.value)"></td>
                                    <td class="text-right"><button class="btn btn-ghost btn-sm text-red-600" wire:click="removeAttendantService({{ $su->id }})"><x-icon name="x" class="size-4" /></button></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-slate-500">Nenhum serviço. O atendente não terá fila.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <form wire:submit="saveAttendant" class="card">
                        <div class="card-header"><h2 class="card-title">Local e comportamento</h2></div>
                        <div class="card-body grid gap-4 sm:grid-cols-3">
                            <x-field label="Local">
                                <select class="input" wire:model="attendant.location_id">
                                    <option value="">—</option>
                                    @foreach ($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                                </select>
                            </x-field>
                            <x-field label="Número" error="attendant.location_number"><input type="number" min="1" class="input" wire:model="attendant.location_number"></x-field>
                            <x-field label="Tipo de atendimento">
                                <select class="input" wire:model="attendant.queue_type">
                                    @foreach ($queueTypes as $qt)<option value="{{ $qt->value }}">{{ $qt->label() }}</option>@endforeach
                                </select>
                            </x-field>
                            @foreach (['call_by_service' => 'Chamar por serviço', 'call_out_of_order' => 'Chamar fora de ordem', 'change_queue_type' => 'Trocar tipo de atendimento'] as $key => $label)
                                <x-field :label="$label">
                                    <select class="input" wire:model="attendant.{{ $key }}">
                                        <option value="">Padrão global</option>
                                        <option value="1">Sim</option>
                                        <option value="0">Não</option>
                                    </select>
                                </x-field>
                            @endforeach
                        </div>
                        <div class="flex justify-end border-t border-slate-200 px-5 py-3 dark:border-slate-800"><button class="btn btn-primary">Salvar</button></div>
                    </form>
                </div>
            @else
                <div class="card card-body text-center text-slate-500">Selecione um atendente para configurar seus serviços e local.</div>
            @endif
        </div>
    @endif
</div>
