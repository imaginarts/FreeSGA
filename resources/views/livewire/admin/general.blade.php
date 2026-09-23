<x-admin-shell>
    <x-page-header title="Configurações gerais" subtitle="Valem para todas as unidades" />

    <div class="space-y-6">
        <form wire:submit="saveAppearance" class="card">
            <div class="card-header"><h2 class="card-title">Aparência</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <x-field label="Nome do sistema" error="appearance.app_name"><input class="input" wire:model="appearance.app_name"></x-field>
                <x-field label="Cor principal" error="appearance.primary_color">
                    <div class="flex gap-2"><input type="color" class="h-9 w-12 rounded border border-slate-300" wire:model.live="appearance.primary_color"><input class="input font-mono" wire:model.live="appearance.primary_color"></div>
                </x-field>
            </div>
            <div class="flex justify-end border-t border-slate-200 px-5 py-3 dark:border-slate-800"><button class="btn btn-primary">Salvar</button></div>
        </form>

        <form wire:submit="saveBehavior" class="card">
            <div class="card-header"><h2 class="card-title">Comportamento do atendimento</h2></div>
            <div class="card-body space-y-4">
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="checkbox mt-0.5" wire:model.live="behavior.priority_swap">
                    <span><b>Intercalar prioridade e normal</b><br><span class="text-slate-500">Depois de N senhas prioritárias seguidas, a próxima chamada ignora a prioridade.</span></span></label>
                @if ($behavior['priority_swap'])
                    <div class="grid gap-4 pl-7 sm:grid-cols-2">
                        <x-field label="Contar por" error="behavior.priority_swap_method">
                            <select class="input" wire:model="behavior.priority_swap_method"><option value="unit">Unidade</option><option value="user">Atendente</option></select>
                        </x-field>
                        <x-field label="Quantidade de prioridades seguidas" error="behavior.priority_swap_count"><input type="number" min="1" max="10" class="input" wire:model="behavior.priority_swap_count"></x-field>
                    </div>
                @endif
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="checkbox mt-0.5" wire:model="behavior.call_by_service">
                    <span><b>Permitir chamar por serviço</b><br><span class="text-slate-500">O atendente escolhe de qual serviço chamar a próxima senha.</span></span></label>
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="checkbox mt-0.5" wire:model="behavior.call_out_of_order">
                    <span><b>Permitir chamar fora de ordem</b><br><span class="text-slate-500">O atendente pode chamar qualquer senha da fila.</span></span></label>
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" class="checkbox mt-0.5" wire:model="behavior.change_queue_type">
                    <span><b>Atendente pode trocar o tipo de atendimento</b><br><span class="text-slate-500">Todos / normal / prioridade / agendamento.</span></span></label>
                <x-field label="Tolerância de atraso para agendamentos (minutos)" error="behavior.appointment_delay" class="max-w-xs">
                    <input type="number" min="0" max="1440" class="input" wire:model="behavior.appointment_delay">
                </x-field>
            </div>
            <div class="flex justify-end border-t border-slate-200 px-5 py-3 dark:border-slate-800"><button class="btn btn-primary">Salvar</button></div>
        </form>

        <form wire:submit="saveOrdering" class="card">
            <div class="card-header"><h2 class="card-title">Ordenação da fila</h2><span class="text-xs text-slate-500">Critérios aplicados em sequência</span></div>
            <div class="card-body space-y-2">
                @foreach ($ordering as $i => $rule)
                    <div class="flex items-center gap-2" wire:key="o-{{ $i }}">
                        <span class="w-6 text-sm text-slate-400">{{ $i + 1 }}º</span>
                        <select class="input" wire:model="ordering.{{ $i }}.field">
                            <option value="">— nenhum —</option>
                            @foreach ($fields as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select>
                        <select class="input w-40" wire:model="ordering.{{ $i }}.order">
                            <option value="asc">Crescente</option>
                            <option value="desc">Decrescente</option>
                        </select>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end border-t border-slate-200 px-5 py-3 dark:border-slate-800"><button class="btn btn-primary">Salvar</button></div>
        </form>

        <div class="card border-red-200 dark:border-red-900">
            <div class="card-header"><h2 class="card-title text-red-700 dark:text-red-400">Dados (todas as unidades)</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="font-medium">Reiniciar senhas</p>
                    <p class="mb-3 text-sm text-slate-500">Arquiva os atendimentos atuais (mantidos nos relatórios), limpa os painéis e volta os contadores ao número inicial.</p>
                    <button class="btn btn-warning" wire:click="archiveAll" wire:confirm="Reiniciar as senhas de TODAS as unidades?"><x-icon name="refresh" class="size-4" /> Reiniciar senhas</button>
                </div>
                <div>
                    <p class="font-medium">Apagar atendimentos</p>
                    <p class="mb-3 text-sm text-slate-500">Remove definitivamente todo o histórico de atendimentos. Não pode ser desfeito.</p>
                    <button class="btn btn-danger" wire:click="clearAll" wire:confirm="ATENÇÃO: apagar definitivamente TODOS os atendimentos de TODAS as unidades?"><x-icon name="trash" class="size-4" /> Apagar tudo</button>
                </div>
            </div>
        </div>
    </div>
</x-admin-shell>
