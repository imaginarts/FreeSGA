<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('API')]
class ApiTokens extends Component
{
    public bool $showForm = false;

    public string $name = '';

    public ?int $userId = null;

    public ?string $plainToken = null;

    public function create(): void
    {
        $this->reset('name', 'plainToken');
        $this->userId = auth()->id();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'userId' => ['required', 'exists:users,id'],
        ], [], ['name' => 'descrição', 'userId' => 'usuário']);

        $this->plainToken = User::findOrFail($this->userId)->createToken($this->name)->plainTextToken;
        $this->showForm = false;
    }

    public function revoke(int $id): void
    {
        PersonalAccessToken::whereKey($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Token revogado.');
    }

    public function render()
    {
        return view('livewire.admin.api-tokens', [
            'tokens' => PersonalAccessToken::with('tokenable')->latest()->get(),
            'users' => User::where('active', true)->orderBy('name')->get(),
        ]);
    }
}
