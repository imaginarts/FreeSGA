<div class="space-y-6">
    {{-- ============================================================ Impressoras --}}
    <div @class(['card', 'opacity-60' => ! $unit->feature('direct_print_enabled')])>
        <div class="card-header">
            <div>
                <h2 class="card-title">Impressoras térmicas</h2>
                <p class="text-xs text-slate-500">ESC/POS: o servidor imprime direto, sem janela de impressão</p>
            </div>
            <button class="btn btn-primary btn-sm" wire:click="createPrinter" @disabled(! $unit->feature('direct_print_enabled'))><x-icon name="plus" class="size-4" /> Nova impressora</button>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Nome</th><th>Conexão</th><th>Papel</th><th>Último uso</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($printers as $p)
                    <tr wire:key="pr-{{ $p->id }}">
                        <td class="font-medium">{{ $p->name }}</td>
                        <td class="font-mono text-xs">{{ $p->address() }}</td>
                        <td>{{ $p->paper_width }} mm</td>
                        <td class="text-xs">
                            {{ $p->last_used_at?->diffForHumans() ?? 'Nunca' }}
                            @if ($p->last_error)<span class="block max-w-64 truncate text-red-600" title="{{ $p->last_error }}">Erro: {{ $p->last_error }}</span>@endif
                        </td>
                        <td><x-active-badge :active="$p->active" /></td>
                        <td class="text-right whitespace-nowrap">
                            <button class="btn btn-ghost btn-sm" wire:click="testPrinter({{ $p->id }})" wire:loading.attr="disabled" title="Imprimir página de teste"><x-icon name="printer" class="size-4" /></button>
                            <button class="btn btn-ghost btn-sm" wire:click="editPrinter({{ $p->id }})"><x-icon name="pencil" class="size-4" /></button>
                            <button class="btn btn-ghost btn-sm text-red-600" wire:click="deletePrinter({{ $p->id }})" wire:confirm="Remover a impressora {{ $p->name }}?"><x-icon name="trash" class="size-4" /></button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Nenhuma impressora cadastrada. Sem impressora, a triagem usa a impressão do navegador.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============================================================ Totens --}}
    <div @class(['card', 'opacity-60' => ! $unit->feature('kiosk_enabled')])>
        <div class="card-header">
            <div>
                <h2 class="card-title">Totens de autoatendimento</h2>
                <p class="text-xs text-slate-500">Tela de toque para o cliente retirar a própria senha</p>
            </div>
            <button class="btn btn-primary btn-sm" wire:click="createKiosk" @disabled(! $unit->feature('kiosk_enabled'))><x-icon name="plus" class="size-4" /> Novo totem</button>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Nome</th><th>Impressão</th><th>Serviços</th><th>Último acesso</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($kiosks as $k)
                    <tr wire:key="k-{{ $k->id }}">
                        <td class="font-medium">{{ $k->name }}</td>
                        <td class="text-xs">{{ $k->print_mode === 'printer' ? $k->printer?->name : ($k->print_mode === 'browser' ? 'Navegador' : 'Não imprime') }}</td>
                        <td class="text-xs">{{ $k->services ? count($k->services).' selecionado(s)' : 'Todos' }}</td>
                        <td class="text-xs">{{ $k->last_seen_at?->diffForHumans() ?? 'Nunca' }}</td>
                        <td><x-active-badge :active="$k->active" /></td>
                        <td class="text-right whitespace-nowrap" x-data>
                            <a href="{{ route('kiosk.show', $k) }}" target="_blank" class="btn btn-ghost btn-sm" title="Abrir totem"><x-icon name="external" class="size-4" /></a>
                            <button class="btn btn-ghost btn-sm" title="Copiar link" @click="navigator.clipboard.writeText(@js(route('kiosk.show', $k))); $dispatch('toast', { type: 'success', message: 'Link copiado!' })"><x-icon name="copy" class="size-4" /></button>
                            <button class="btn btn-ghost btn-sm" wire:click="editKiosk({{ $k->id }})"><x-icon name="pencil" class="size-4" /></button>
                            <button class="btn btn-ghost btn-sm text-red-600" wire:click="deleteKiosk({{ $k->id }})" wire:confirm="Remover o totem {{ $k->name }}?"><x-icon name="trash" class="size-4" /></button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Nenhum totem cadastrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500 dark:border-slate-800">
            <b>Dica:</b> no computador do totem, abra o Chrome em modo quiosque. Com <code>--kiosk-printing</code> a impressão pelo navegador também sai sem janela:
            <code class="mt-1 block overflow-x-auto rounded bg-slate-100 px-2 py-1 dark:bg-slate-800">chrome.exe --kiosk --kiosk-printing "{{ $kiosks->first() ? route('kiosk.show', $kiosks->first()) : url('/totem/...') }}"</code>
        </div>
    </div>

    {{-- Formulário de impressora --}}
    <x-modal wire:model="showPrinter" :title="$printerId ? 'Editar impressora' : 'Nova impressora'" max-width="max-w-xl">
        <form wire:submit="savePrinter" class="space-y-4">
            <x-field label="Nome" error="printer.name"><input class="input" wire:model="printer.name" placeholder="Ex.: Recepção"></x-field>
            <x-field label="Conexão">
                <select class="input" wire:model.live="printer.connection_type">
                    @foreach ($connections as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </x-field>
            @if (($printer['connection_type'] ?? 'network') === 'network')
                <div class="grid grid-cols-[1fr_120px] gap-3">
                    <x-field label="IP da impressora" error="printer.host"><input class="input font-mono" wire:model="printer.host" placeholder="192.168.0.50"></x-field>
                    <x-field label="Porta" error="printer.port"><input type="number" class="input" wire:model="printer.port"></x-field>
                </div>
            @else
                <x-field label="Nome do compartilhamento" error="printer.host" hint="Compartilhe a impressora USB no Windows do servidor e informe o nome (ex.: TERMICA) ou smb://computador/TERMICA">
                    <input class="input font-mono" wire:model="printer.host">
                </x-field>
            @endif
            <x-field label="Largura do papel">
                <select class="input" wire:model="printer.paper_width"><option value="80">80 mm</option><option value="58">58 mm</option></select>
            </x-field>
            <div class="grid gap-2 sm:grid-cols-2">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="printer.cut"> Cortar papel</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="printer.print_qr"> Imprimir QR code</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="printer.strip_accents"> Remover acentos</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="printer.active"> Ativa</label>
            </div>
            <p class="text-xs text-slate-500">Se os acentos saírem errados, marque "Remover acentos".</p>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </x-modal>

    {{-- Formulário de totem --}}
    <x-modal wire:model="showKiosk" :title="$kioskId ? 'Editar totem' : 'Novo totem'" max-width="max-w-3xl">
        <form wire:submit="saveKiosk" class="space-y-5">
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field label="Nome" error="kiosk.name"><input class="input" wire:model="kiosk.name" placeholder="Ex.: Totem da entrada"></x-field>
                <x-field label="Cor principal"><input type="color" class="h-10 w-full rounded-lg border border-slate-300" wire:model="kiosk.settings.primary_color"></x-field>
                <x-field label="Título de boas-vindas"><input class="input" wire:model="kiosk.settings.welcome_title"></x-field>
                <x-field label="Texto de boas-vindas"><input class="input" wire:model="kiosk.settings.welcome_text"></x-field>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <x-field label="Impressão">
                    <select class="input" wire:model.live="kiosk.print_mode">
                        @foreach ($printModes as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </x-field>
                @if (($kiosk['print_mode'] ?? '') === 'printer')
                    <x-field label="Impressora" error="kiosk.printer_id">
                        <select class="input" wire:model="kiosk.printer_id">
                            <option value="">Selecione…</option>
                            @foreach ($printers->where('active', true) as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                        </select>
                    </x-field>
                @endif
            </div>

            <x-field label="Serviços exibidos" hint="Nenhum marcado = todos os serviços ativos da unidade">
                <div class="grid max-h-40 gap-1.5 overflow-y-auto rounded-lg border border-slate-200 p-3 sm:grid-cols-2 dark:border-slate-700">
                    @foreach ($unitServices as $us)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" value="{{ $us->service_id }}" wire:model="kiosk.services"> {{ $us->service->name }}</label>
                    @endforeach
                </div>
            </x-field>

            <div class="grid gap-2 sm:grid-cols-2">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="kiosk.settings.show_priority"> Botão de atendimento preferencial</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="kiosk.settings.appointments"> "Tenho agendamento" (check-in por CPF)</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model.live="kiosk.settings.ask_document"> Pedir CPF antes de emitir</label>
                @if ($kiosk['settings']['ask_document'] ?? false)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="kiosk.settings.require_document"> CPF obrigatório</label>
                @endif
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="kiosk.settings.ask_phone"> Oferecer aviso por WhatsApp</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="kiosk.settings.show_eta"> Mostrar espera estimada</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="kiosk.settings.show_qr"> Mostrar QR de acompanhamento</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="kiosk.settings.high_contrast"> Alto contraste</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="kiosk.active"> Totem ativo</label>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field label="Voltar ao início após emitir (segundos)" error="kiosk.settings.reset_seconds"><input type="number" class="input" wire:model="kiosk.settings.reset_seconds"></x-field>
                <x-field label="Voltar ao início por inatividade (segundos)" error="kiosk.settings.idle_seconds"><input type="number" class="input" wire:model="kiosk.settings.idle_seconds"></x-field>
            </div>
            <p class="text-xs text-slate-500">O QR code de acompanhamento só aparece se "Senha no celular" estiver habilitada na unidade.</p>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </x-modal>
</div>
