<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Meu perfil')]
class Profile extends Component
{
    public string $name = '';

    public string $last_name = '';

    public ?string $email = null;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->last_name = $user->last_name;
        $this->email = $user->email;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users')->ignore(auth()->id())],
        ], [], ['name' => 'nome', 'last_name' => 'sobrenome']);

        auth()->user()->update([...$data, 'last_name' => $data['last_name'] ?? '']);
        $this->dispatch('toast', type: 'success', message: 'Perfil atualizado.');
    }

    public function changePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ], [], ['current_password' => 'senha atual', 'password' => 'nova senha']);

        auth()->user()->update(['password' => Hash::make($this->password)]);
        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('toast', type: 'success', message: 'Senha alterada.');
    }

    public function render()
    {
        return view('livewire.profile', [
            'allocations' => auth()->user()->allocations()->with(['unit', 'role'])->get(),
        ]);
    }
}
