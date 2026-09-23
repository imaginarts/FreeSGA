<div>
    <x-page-header title="Painéis de chamada" subtitle="Abra o link do painel na TV da recepção (não precisa de login)">
        <button class="btn btn-primary" wire:click="create"><x-icon name="plus" class="size-4" /> Novo painel</button>
    </x-page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($panels as $p)
            <div class="card" wire:key="p-{{ $p->id }}">
                <div class="flex h-24 items-center justify-center rounded-t-xl font-mono text-4xl font-bold text-white" style="background: linear-gradient(90deg, {{ $p->setting('highlight_bg') }} 65%, {{ $p->setting('history_bg') }} 65%)">A001</div>
                <div class="card-body">
                    <p class="font-semibold">{{ $p->name }}</p>
                    <p class="text-sm text-slate-500">{{ $p->services_count }} serviço(s) · voz {{ $p->setting('voice') ? 'ligada' : 'desligada' }}</p>
                    <div class="mt-4 flex flex-wrap gap-2" x-data>
                        <a href="{{ route('panel.display', $p) }}" target="_blank" class="btn btn-primary btn-sm"><x-icon name="external" class="size-4" /> Abrir painel</a>
                        <button class="btn btn-secondary btn-sm" @click="navigator.clipboard.writeText(@js(route('panel.display', $p))); $dispatch('toast', { type: 'success', message: 'Link copiado!' })"><x-icon name="copy" class="size-4" /> Copiar link</button>
                        <button class="btn btn-ghost btn-sm" wire:click="edit({{ $p->id }})"><x-icon name="pencil" class="size-4" /></button>
                        <button class="btn btn-ghost btn-sm text-red-600" wire:click="delete({{ $p->id }})" wire:confirm="Remover o painel {{ $p->name }}?"><x-icon name="trash" class="size-4" /></button>
                    </div>
                </div>
            </div>
        @empty
            <div class="card card-body col-span-full text-center text-slate-500">Nenhum painel criado para esta unidade.</div>
        @endforelse
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar painel' : 'Novo painel'" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <x-field label="Nome" error="name"><input class="input" wire:model="name" placeholder="Ex.: TV da recepção"></x-field>
            <x-field label="Serviços exibidos" error="services">
                <div class="grid max-h-48 gap-1.5 overflow-y-auto rounded-lg border border-slate-200 p-3 sm:grid-cols-2 dark:border-slate-700">
                    @foreach ($unitServices as $us)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" value="{{ $us->service_id }}" wire:model="services"> <span class="font-mono text-slate-400">{{ $us->prefix }}</span> {{ $us->service->name }}</label>
                    @endforeach
                </div>
            </x-field>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach (['highlight_bg' => 'Fundo do destaque', 'history_bg' => 'Fundo do histórico', 'footer_bg' => 'Fundo do rodapé'] as $key => $label)
                    <x-field :label="$label"><input type="color" class="h-10 w-full rounded-lg border border-slate-300" wire:model="settings.{{ $key }}"></x-field>
                @endforeach
            </div>
            <x-field label="Texto do rodapé"><input class="input" wire:model="settings.footer_text" placeholder="Ex.: Horário de atendimento: 8h às 17h"></x-field>
            <div class="flex flex-wrap gap-x-6 gap-y-2">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="settings.sound"> Tocar som de chamada</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="settings.voice"> Anunciar por voz</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="settings.show_eta"> Exibir tempo estimado de espera</label>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </x-modal>
</div>
