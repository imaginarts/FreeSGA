<div>
    <x-page-header title="Agendamentos" subtitle="A chegada do cliente é confirmada na Triagem, gerando a senha">
        <input type="date" class="input w-44" wire:model.live="date">
        <select class="input w-40" wire:model.live="status">
            <option value="">Todas situações</option>
            @foreach ($statuses as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
        </select>
        <input class="input w-48" placeholder="Cliente…" wire:model.live.debounce.300ms="search">
        <button class="btn btn-primary" wire:click="create"><x-icon name="plus" class="size-4" /> Novo agendamento</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Data / hora</th><th>Cliente</th><th>Serviço</th><th>Situação</th><th>Senha</th><th></th></tr></thead>
            <tbody>
            @forelse ($appointments as $a)
                <tr wire:key="a-{{ $a->id }}">
                    <td class="font-mono">{{ $a->date->format('d/m/Y') }} {{ substr($a->time, 0, 5) }}</td>
                    <td><p class="font-medium">{{ $a->customer->name }}</p><p class="text-xs text-slate-500">{{ \App\Support\Privacy::document($a->customer->document) }}</p></td>
                    <td>{{ $a->service->name }}</td>
                    <td>
                        <span @class(['badge',
                            'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => $a->status === \App\Enums\AppointmentStatus::Scheduled,
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $a->status === \App\Enums\AppointmentStatus::Confirmed,
                            'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $a->status === \App\Enums\AppointmentStatus::NoShow])>{{ $a->status->label() }}</span>
                    </td>
                    <td class="font-mono">{{ $a->ticket?->code() ?? '—' }}</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($a->status === \App\Enums\AppointmentStatus::Scheduled)
                            <button class="btn btn-ghost btn-sm" wire:click="edit({{ $a->id }})"><x-icon name="pencil" class="size-4" /></button>
                        @endif
                        @if ($a->status !== \App\Enums\AppointmentStatus::Confirmed)
                            <button class="btn btn-ghost btn-sm text-red-600" wire:click="delete({{ $a->id }})" wire:confirm="Remover este agendamento?"><x-icon name="trash" class="size-4" /></button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-slate-500">Nenhum agendamento para o filtro selecionado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $appointments->links() }}</div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Editar agendamento' : 'Novo agendamento'" max-width="max-w-xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Data" error="form.date"><input type="date" class="input" wire:model="form.date"></x-field>
                <x-field label="Hora" error="form.time"><input type="time" class="input" wire:model="form.time"></x-field>
            </div>
            <x-field label="Serviço" error="form.service_id">
                <select class="input" wire:model="form.service_id">
                    <option value="">Selecione…</option>
                    @foreach ($services as $us)<option value="{{ $us->service_id }}">{{ $us->service->name }}</option>@endforeach
                </select>
            </x-field>

            <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                <div class="mb-2 flex items-center justify-between">
                    <span class="label !mb-0">Cliente</span>
                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" class="checkbox" wire:model.live="newCustomer"> Cadastrar novo</label>
                </div>
                @if ($newCustomer)
                    <div class="grid gap-3 sm:grid-cols-3">
                        <x-field label="Nome" error="form.customer_name" class="sm:col-span-3"><input class="input" wire:model="form.customer_name"></x-field>
                        <x-field label="Documento" error="form.customer_document" class="sm:col-span-2"><input class="input" wire:model="form.customer_document"></x-field>
                        <x-field label="Telefone"><input class="input" wire:model="form.customer_phone"></x-field>
                    </div>
                @else
                    <div class="relative">
                        <input class="input" placeholder="Buscar por nome ou documento…" wire:model.live.debounce.300ms="customerSearch">
                        @if ($customerOptions->isNotEmpty())
                            <ul class="absolute z-10 mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                @foreach ($customerOptions as $c)
                                    <li><button type="button" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800" wire:click="selectCustomer({{ $c->id }})">{{ $c->name }} <span class="text-slate-500">· {{ \App\Support\Privacy::document($c->document) }}</span></button></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    @error('form.customer_id')<p class="error">{{ $message }}</p>@enderror
                @endif
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </x-modal>
</div>
