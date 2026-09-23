<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithUnit;
use App\Models\Allocation;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Usuários')]
class Users extends Component
{
    use InteractsWithUnit, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    /** @var array<int, array{unit_id: int|string, role_id: int|string}> */
    public array $allocations = [];

    public bool $showPassword = false;

    public string $password = '';

    public string $password_confirmation = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->form = ['login' => '', 'name' => '', 'last_name' => '', 'email' => '', 'active' => true, 'is_admin' => false, 'password' => '', 'password_confirmation' => ''];
        $this->allocations = [['unit_id' => $this->unit()->id, 'role_id' => Role::orderBy('name')->value('id')]];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = $this->findEditable($id);
        $this->resetErrorBag();
        $this->editingId = $user->id;
        $this->form = [...$user->only(['login', 'name', 'last_name', 'email', 'active', 'is_admin']), 'email' => $user->email ?? ''];
        $this->allocations = $user->allocations()
            ->when(! $this->user()->is_admin, fn ($q) => $q->whereIn('unit_id', $this->manageableUnits()->pluck('id')))
            ->get(['unit_id', 'role_id'])
            ->toArray();
        $this->showForm = true;
    }

    public function addAllocation(): void
    {
        $used = array_column($this->allocations, 'unit_id');
        $unit = $this->manageableUnits()->reject(fn ($u) => in_array($u->id, $used))->first();

        if (! $unit) {
            $this->toast('Não há outras unidades disponíveis.', 'error');

            return;
        }

        $this->allocations[] = ['unit_id' => $unit->id, 'role_id' => Role::orderBy('name')->value('id')];
    }

    public function removeAllocation(int $index): void
    {
        unset($this->allocations[$index]);
        $this->allocations = array_values($this->allocations);
    }

    public function save(): void
    {
        $manageable = $this->manageableUnits()->pluck('id')->all();
        $isAdmin = $this->user()->is_admin;

        $rules = [
            'form.login' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'login')->ignore($this->editingId)],
            'form.name' => ['required', 'string', 'min:2', 'max:50'],
            'form.last_name' => ['nullable', 'string', 'max:100'],
            'form.email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->editingId)],
            'form.active' => ['boolean'],
            'allocations' => [$isAdmin && ($this->form['is_admin'] ?? false) ? 'array' : 'required', 'array'],
            'allocations.*.unit_id' => ['required', 'distinct', Rule::in($isAdmin ? Unit::pluck('id') : $manageable)],
            'allocations.*.role_id' => ['required', 'exists:roles,id'],
        ];
        if (! $this->editingId) {
            $rules['form.password'] = ['required', 'confirmed', Password::min(6)];
        }

        $this->validate($rules, ['allocations.required' => 'Informe ao menos uma lotação.', 'allocations.*.unit_id.distinct' => 'Unidade repetida.'], [
            'form.login' => 'login', 'form.name' => 'nome', 'form.password' => 'senha',
        ]);

        DB::transaction(function () use ($isAdmin, $manageable) {
            $data = [
                'login' => $this->form['login'],
                'name' => $this->form['name'],
                'last_name' => $this->form['last_name'] ?? '',
                'email' => $this->form['email'] ?: null,
                'active' => (bool) $this->form['active'],
            ];
            if ($isAdmin) {
                $data['is_admin'] = (bool) $this->form['is_admin'];
            }

            $user = $this->editingId ? tap($this->findEditable($this->editingId))->update($data) : User::create([...$data, 'password' => Hash::make($this->form['password'])]);

            // um gestor só mexe nas lotações das unidades que ele gerencia
            $scope = $isAdmin ? null : $manageable;
            $keep = collect($this->allocations)->pluck('unit_id')->map('intval');

            Allocation::where('user_id', $user->id)
                ->when($scope, fn ($q) => $q->whereIn('unit_id', $scope))
                ->whereNotIn('unit_id', $keep)
                ->delete();

            foreach ($this->allocations as $a) {
                Allocation::updateOrCreate(['user_id' => $user->id, 'unit_id' => $a['unit_id']], ['role_id' => $a['role_id']]);
            }
        });

        $this->showForm = false;
        $this->toast('Usuário salvo.');
    }

    public function openPassword(int $id): void
    {
        $this->findEditable($id);
        $this->editingId = $id;
        $this->reset('password', 'password_confirmation');
        $this->resetErrorBag();
        $this->showPassword = true;
    }

    public function savePassword(): void
    {
        $this->validate(['password' => ['required', 'confirmed', Password::min(6)]], [], ['password' => 'senha']);
        $this->findEditable($this->editingId)->update(['password' => Hash::make($this->password)]);
        $this->showPassword = false;
        $this->toast('Senha alterada.');
    }

    /** Admin gerencia todas as unidades; gestor apenas as suas. */
    private function manageableUnits()
    {
        return $this->user()->availableUnits();
    }

    private function findEditable(int $id): User
    {
        $user = User::findOrFail($id);

        if (! $this->user()->is_admin) {
            abort_if($user->is_admin, 403);
            abort_unless($user->allocations()->whereIn('unit_id', $this->manageableUnits()->pluck('id'))->exists(), 403);
        }

        return $user;
    }

    public function getListeners(): array
    {
        return [];
    }

    public function render()
    {
        $term = trim($this->search);

        return view('livewire.users', [
            'users' => User::with(['allocations.unit', 'allocations.role'])
                ->when(! $this->user()->is_admin, fn ($q) => $q->where('is_admin', false)->whereHas('allocations', fn ($a) => $a->where('unit_id', $this->unit()->id)))
                ->when($term, fn ($q) => $q->where(fn ($q) => $q->where('login', 'like', "%$term%")->orWhere('name', 'like', "%$term%")->orWhere('last_name', 'like', "%$term%")))
                ->orderBy('name')->paginate(20),
            'units' => $this->manageableUnits(),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }
}
