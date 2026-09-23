<x-admin-shell>
    <x-page-header :title="$title" :subtitle="$subtitle">
        <button class="btn btn-primary" wire:click="create"><x-icon name="plus" class="size-4" /> Novo {{ $singular }}</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr>@foreach ($columns as $label)<th>{{ $label }}</th>@endforeach<th></th></tr></thead>
            <tbody>
            @forelse ($records as $r)
                <tr wire:key="r-{{ $r->id }}">
                    @foreach ($columns as $key => $label)
                        <td>
                            @if ($key === 'active')<x-active-badge :active="$r->active" />@else{{ $r->{$key} }}@endif
                        </td>
                    @endforeach
                    <td class="text-right whitespace-nowrap">
                        <button class="btn btn-ghost btn-sm" wire:click="edit({{ $r->id }})"><x-icon name="pencil" class="size-4" /></button>
                        <button class="btn btn-ghost btn-sm text-red-600" wire:click="delete({{ $r->id }})" wire:confirm="Remover {{ $r->name }}?"><x-icon name="trash" class="size-4" /></button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + 1 }}" class="text-center text-slate-500">Nenhum registro.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>

    <x-modal wire:model="showForm" :title="($editingId ? 'Editar ' : 'Novo ').$singular">
        <form wire:submit="save" class="space-y-4">
            @foreach ($fields as [$key, $label, $type])
                @if ($type === 'checkbox')
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" wire:model="form.{{ $key }}"> {{ $label }}</label>
                @else
                    <x-field :label="$label" :error="'form.'.$key"><input class="input" type="{{ $type }}" wire:model="form.{{ $key }}"></x-field>
                @endif
            @endforeach
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" @click="show = false">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </x-modal>
</x-admin-shell>
