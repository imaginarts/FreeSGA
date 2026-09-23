<?php

namespace App\Livewire\Concerns;

use App\Exceptions\TicketException;
use App\Models\Unit;
use App\Models\User;

trait InteractsWithUnit
{
    protected function user(): User
    {
        return auth()->user();
    }

    protected function unit(): Unit
    {
        $unit = $this->user()->currentUnit;
        abort_unless($unit, 403, 'Nenhuma unidade selecionada.');

        return $unit;
    }

    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }

    /** Executa uma regra de negócio exibindo o erro como toast. */
    protected function attempt(callable $action, ?string $success = null): mixed
    {
        try {
            $result = $action();
            if ($success) {
                $this->toast($success);
            }

            return $result;
        } catch (TicketException $e) {
            $this->toast($e->getMessage(), 'error');

            return null;
        }
    }

    /** Recarrega o componente quando a fila da unidade muda (Reverb). */
    public function getListeners(): array
    {
        $unitId = auth()->user()?->current_unit_id;

        return $unitId ? ["echo:unit.{$unitId},.queue.updated" => '$refresh'] : [];
    }
}
