<x-admin-shell>
    <x-page-header title="Mensagens (WhatsApp/SMS)" subtitle="Provedor usado pelos avisos da fila e pela pesquisa de satisfação. Cada unidade liga os avisos em Configurações da unidade." />

    <form wire:submit="save" class="space-y-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Provedor</h2></div>
            <div class="card-body space-y-4">
                <x-field label="Enviar mensagens por" error="config.provider" class="max-w-md">
                    <select class="input" wire:model.live="config.provider">
                        @foreach ($providers as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </x-field>

                @if ($config['provider'] === 'whatsapp_cloud')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="Phone number ID" error="config.whatsapp_phone_number_id"><input class="input font-mono" wire:model="config.whatsapp_phone_number_id"></x-field>
                        <x-field label="Token de acesso" error="whatsappToken" :hint="$hasToken ? 'Já configurado. Preencha apenas para trocar.' : 'Token permanente do usuário do sistema (Meta Business).'">
                            <input type="password" class="input font-mono" wire:model="whatsappToken" autocomplete="off" placeholder="{{ $hasToken ? '••••••••' : '' }}">
                        </x-field>
                        <x-field label="Versão da API"><input class="input font-mono" wire:model="config.whatsapp_api_version"></x-field>
                        <x-field label="Idioma dos modelos"><input class="input font-mono" wire:model="config.whatsapp_language"></x-field>
                    </div>
                    <div>
                        <p class="label">Modelos aprovados (nome do template)</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($types as $key => $label)
                                <x-field :label="$label"><input class="input font-mono" wire:model="config.whatsapp_templates.{{ $key }}" placeholder="ex.: sga_{{ $key }}"></x-field>
                            @endforeach
                        </div>
                        <p class="hint">A Meta só permite iniciar conversas com modelos aprovados. Os parâmetros do modelo seguem a ordem: @{{1}}, @{{2}}… com os valores do aviso (ex.: senha chamada → senha, local). Sem modelo, é enviado texto livre (só funciona dentro da janela de 24 h de conversa).</p>
                    </div>
                @elseif ($config['provider'] === 'http')
                    <x-field label="URL da API" error="config.http_url" hint="Ex.: Z-API https://api.z-api.io/instances/ID/token/TOKEN/send-text · Evolution https://SEU-HOST/message/sendText/INSTANCIA">
                        <input class="input font-mono" wire:model="config.http_url">
                    </x-field>
                    <x-field label="Cabeçalhos HTTP (um por linha)" error="httpHeaders" :hint="$hasHeaders ? 'Já configurados (criptografados). Preencha apenas para trocar.' : 'Ex.: apikey: SUA-CHAVE ou Client-Token: …'">
                        <textarea class="input font-mono" rows="3" wire:model="httpHeaders" placeholder="{{ $hasHeaders ? '••••••••' : 'Authorization: Bearer …' }}"></textarea>
                    </x-field>
                    <x-field label="Corpo JSON" hint="Use {telefone} e {mensagem}. Ex. Evolution: {&quot;number&quot;: &quot;{telefone}&quot;, &quot;text&quot;: &quot;{mensagem}&quot;}">
                        <textarea class="input font-mono" rows="3" wire:model="config.http_body"></textarea>
                    </x-field>
                @elseif ($config['provider'] === 'log')
                    <p class="text-sm text-slate-500">As mensagens são apenas gravadas em <code>storage/logs/laravel.log</code>. Útil para testar o fluxo sem enviar nada.</p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Textos das mensagens</h2><span class="text-xs text-slate-500">Variáveis: {senha} {servico} {unidade} {local} {posicao} {link}</span></div>
            <div class="card-body grid gap-4 lg:grid-cols-2">
                @foreach ($types as $key => $label)
                    <x-field :label="$label" :error="'config.texts.'.$key"><textarea class="input" rows="3" wire:model="config.texts.{{ $key }}"></textarea></x-field>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end"><button class="btn btn-primary">Salvar</button></div>
    </form>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Enviar mensagem de teste</h2></div>
            <form wire:submit="sendTest" class="card-body space-y-3">
                <x-field label="Telefone (DDD + número)" error="testPhone"><input class="input" type="tel" wire:model="testPhone" placeholder="(11) 98765-4321"></x-field>
                <x-field label="Tipo">
                    <select class="input" wire:model="testType">
                        @foreach ($types as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </x-field>
                <p class="text-xs text-slate-500">Salve a configuração antes de testar.</p>
                <button class="btn btn-secondary" wire:loading.attr="disabled">Enviar teste</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Últimos envios</h2></div>
            <div class="max-h-80 overflow-y-auto">
                <table class="table">
                    <thead><tr><th>Quando</th><th>Senha</th><th>Tipo</th><th>Para</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($recent as $n)
                        <tr>
                            <td class="text-xs">{{ $n->created_at->diffForHumans() }}</td>
                            <td class="font-mono">{{ $n->ticket?->code() }}</td>
                            <td class="text-xs">{{ $types[$n->type] ?? $n->type }}</td>
                            <td class="font-mono text-xs">{{ \App\Support\Phone::masked($n->to) }}</td>
                            <td>
                                @if ($n->status === 'sent')
                                    <span class="badge bg-emerald-100 text-emerald-700">Enviada</span>
                                @else
                                    <span class="badge bg-red-100 text-red-700" title="{{ $n->error }}">Falhou</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-slate-500">Nenhuma mensagem enviada ainda.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-shell>
