<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Livewire\WithPagination;

/**
 * CRUD simples com formulário em modal.
 * O componente define model(), rules(), fill*()/payload() e a propriedade $form.
 */
trait CrudModal
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $search = '';

    public array $form = [];

    abstract protected function model(): string;

    abstract protected function defaults(): array;

    abstract protected function rules(): array;

    protected function payload(array $data): array
    {
        return $data;
    }

    protected function afterSave(Model $record, array $data): void {}

    protected function toForm(Model $record): array
    {
        return array_intersect_key($record->toArray(), $this->defaults());
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->form = $this->defaults();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetErrorBag();
        $record = $this->model()::findOrFail($id);
        $this->editingId = $id;
        $this->form = array_replace($this->defaults(), $this->toForm($record));
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate($this->rules(), [], $this->attributes())['form'] ?? [];
        $data = $this->payload($data);

        $record = $this->editingId
            ? tap($this->model()::findOrFail($this->editingId))->update($data)
            : $this->model()::create($data);

        $this->afterSave($record, $data);
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Registro salvo.');
    }

    public function delete(int $id): void
    {
        $record = $this->model()::findOrFail($id);

        if ($reason = $this->cannotDelete($record)) {
            $this->dispatch('toast', type: 'error', message: $reason);

            return;
        }

        try {
            $record->delete();
            $this->dispatch('toast', type: 'success', message: 'Registro removido.');
        } catch (QueryException) {
            $this->dispatch('toast', type: 'error', message: 'Não é possível remover: o registro está em uso.');
        }
    }

    protected function cannotDelete(Model $record): ?string
    {
        return null;
    }

    protected function attributes(): array
    {
        return [];
    }
}
