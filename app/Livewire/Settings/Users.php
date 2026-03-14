<?php

namespace App\Livewire\Settings;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class Users extends Component
{
    use WithPagination;

    public bool    $showModal  = false;
    public ?int    $editingId  = null;

    public string  $name       = '';
    public string  $email      = '';
    public string  $password   = '';
    public string  $role       = '';
    public ?int    $company_id = null;
    public array   $outletIds  = [];

    public string  $search     = '';

    public function updatedSearch(): void { $this->resetPage(); }

    // ── Roles this user may assign ──────────────────────────────────────────

    public function assignableRoles(): array
    {
        $user = Auth::user();

        if ($user->hasRole('Super Admin') || $user->hasRole('System Admin')) {
            return ['System Admin', 'Business Manager', 'Operations Manager', 'Branch Manager', 'Chef', 'Purchasing', 'Finance'];
        }

        return ['Operations Manager', 'Branch Manager', 'Chef', 'Purchasing', 'Finance'];
    }

    // ── Open / Close ─────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $user = User::with('outlets')->findOrFail($id);
        $currentUser = Auth::user();

        // Business Manager cannot edit Super Admin or System Admin users
        if (! $currentUser->hasRole(['Super Admin', 'System Admin'])) {
            $targetRole = $user->roles->first()?->name;
            if (in_array($targetRole, ['Super Admin', 'System Admin'])) {
                session()->flash('error', 'You cannot edit this user.');
                return;
            }
        }

        $this->editingId  = $user->id;
        $this->name       = $user->name;
        $this->email      = $user->email;
        $this->password   = '';
        $this->role       = $user->roles->first()?->name ?? '';
        $this->company_id = $user->company_id;
        $this->outletIds  = $user->outlets->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        $this->showModal  = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    // ── Save ─────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $rules = [
            'name'      => 'required|string|max:100',
            'email'     => ['required', 'email', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role'      => ['required', 'string', Rule::in($this->assignableRoles())],
            'outletIds' => 'array',
        ];

        if (! $this->editingId) {
            $rules['password'] = 'required|string|min:8';
        } else {
            $rules['password'] = 'nullable|string|min:8';
        }

        $this->validate($rules);

        $currentUser = Auth::user();
        $companyId   = $currentUser->hasRole(['Super Admin', 'System Admin'])
            ? $this->company_id
            : $currentUser->company_id;

        // Default outlet_id to first selected outlet (for backward compat)
        $primaryOutletId = ! empty($this->outletIds) ? (int) $this->outletIds[0] : null;

        $data = [
            'name'       => $this->name,
            'email'      => $this->email,
            'company_id' => $companyId,
            'outlet_id'  => $primaryOutletId,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $user->update($data);
            $user->syncRoles([$this->role]);
            session()->flash('success', 'User updated.');
        } else {
            $data['email_verified_at'] = now();
            $user = User::create($data);
            $user->assignRole($this->role);
            session()->flash('success', 'User created.');
        }

        // Sync outlet access
        $user->outlets()->sync(array_map('intval', $this->outletIds));

        $this->closeModal();
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function delete(int $id): void
    {
        if ($id === Auth::id()) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }

        $user = User::findOrFail($id);
        $user->outlets()->detach();
        $user->delete();
        session()->flash('success', 'User deleted.');
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser->hasRole(['Super Admin', 'System Admin']);

        $query = User::with(['roles', 'outlets'])->when($this->search, fn ($q) =>
            $q->where('name', 'like', '%'.$this->search.'%')
              ->orWhere('email', 'like', '%'.$this->search.'%')
        );

        if (! $isSuperAdmin) {
            $query->where('company_id', $currentUser->company_id);
            // Hide Super Admin and System Admin from Business Manager
            $query->whereDoesntHave('roles', fn ($q) =>
                $q->whereIn('name', ['Super Admin', 'System Admin'])
            );
        }

        $users     = $query->orderBy('name')->paginate(20);
        $companies = $isSuperAdmin ? Company::orderBy('name')->get() : collect();
        $outlets   = Outlet::when(! $isSuperAdmin, fn ($q) =>
            $q->where('company_id', $currentUser->company_id)
        )->where('is_active', true)->orderBy('name')->get();

        $assignableRoles = $this->assignableRoles();

        return view('livewire.settings.users', compact(
            'users', 'companies', 'outlets', 'assignableRoles', 'isSuperAdmin'
        ))->layout('layouts.app', ['title' => 'Users & Roles']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->editingId  = null;
        $this->name       = '';
        $this->email      = '';
        $this->password   = '';
        $this->role       = '';
        $this->company_id = null;
        $this->outletIds  = [];
        $this->resetValidation();
    }
}
