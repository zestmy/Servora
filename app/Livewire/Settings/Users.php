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

class Users extends Component
{
    use WithPagination;

    public bool    $showModal  = false;
    public ?int    $editingId  = null;

    public string  $name       = '';
    public string  $email      = '';
    public string  $password   = '';
    public string  $designation = '';
    public ?int    $company_id = null;

    // Module access (maps to Spatie permissions)
    public array   $moduleAccess = [];

    // Outlets
    public bool    $allOutlets  = false;
    public array   $outletIds   = [];

    // Capabilities
    public bool    $can_manage_users    = false;
    public bool    $can_approve_po      = false;
    public bool    $can_approve_pr      = false;
    public bool    $can_delete_records  = false;
    public bool    $can_view_all_outlets = false;

    public string  $search     = '';

    // Available modules (permission name => display label)
    public const MODULES = [
        'ingredients.view' => 'Ingredients',
        'recipes.view'     => 'Recipes',
        'purchasing.view'  => 'Purchasing',
        'sales.view'       => 'Sales',
        'inventory.view'   => 'Inventory & Kitchen',
        'reports.view'     => 'Reports',
        'settings.view'    => 'Settings',
    ];

    public function updatedSearch(): void { $this->resetPage(); }

    public function updatedAllOutlets(): void
    {
        if ($this->allOutlets) {
            $this->can_view_all_outlets = true;
        }
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $user = User::with('outlets')->findOrFail($id);
        $currentUser = Auth::user();

        if (! $currentUser->isSystemRole() && $user->isSystemRole()) {
            session()->flash('error', 'You cannot edit this user.');
            return;
        }

        $this->editingId           = $user->id;
        $this->name                = $user->name;
        $this->email               = $user->email;
        $this->password            = '';
        $this->designation         = $user->designation ?? '';
        $this->company_id          = $user->company_id;
        $this->can_manage_users    = $user->can_manage_users;
        $this->can_approve_po      = $user->can_approve_po;
        $this->can_approve_pr      = $user->can_approve_pr;
        $this->can_delete_records  = $user->can_delete_records;
        $this->can_view_all_outlets = $user->can_view_all_outlets;

        // Load current permissions as module access
        $this->moduleAccess = $user->getDirectPermissions()->pluck('name')->toArray();
        // Also include role-based permissions for display
        $allPerms = $user->getAllPermissions()->pluck('name')->toArray();
        $this->moduleAccess = array_values(array_unique(array_merge($this->moduleAccess, $allPerms)));

        // Outlets
        $this->outletIds = $user->outlets->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        $companyOutletCount = Outlet::where('company_id', $user->company_id)->count();
        $this->allOutlets = count($this->outletIds) >= $companyOutletCount && $companyOutletCount > 0;

        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'name'        => 'required|string|max:100',
            'email'       => ['required', 'email', Rule::unique('users', 'email')->ignore($this->editingId)],
            'designation' => 'nullable|string|max:100',
            'outletIds'   => 'array',
        ];

        if (! $this->editingId) {
            $rules['password'] = 'required|string|min:8';
        } else {
            $rules['password'] = 'nullable|string|min:8';
        }

        $this->validate($rules);

        $currentUser = Auth::user();
        $companyId = $currentUser->isSystemRole()
            ? $this->company_id
            : $currentUser->company_id;

        $primaryOutletId = ! empty($this->outletIds) ? (int) $this->outletIds[0] : null;

        $data = [
            'name'                 => $this->name,
            'email'                => $this->email,
            'company_id'           => $companyId,
            'outlet_id'            => $primaryOutletId,
            'designation'          => $this->designation ?: null,
            'can_manage_users'     => $this->can_manage_users,
            'can_approve_po'       => $this->can_approve_po,
            'can_approve_pr'       => $this->can_approve_pr,
            'can_delete_records'   => $this->can_delete_records,
            'can_view_all_outlets' => $this->can_view_all_outlets,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $user->update($data);
            session()->flash('success', 'User updated.');
        } else {
            $data['email_verified_at'] = now();
            $user = User::create($data);
            session()->flash('success', 'User created.');
        }

        // Sync module permissions (direct, not via role)
        $validPermissions = array_intersect($this->moduleAccess, array_keys(self::MODULES));
        // Add users.manage if can_manage_users
        if ($this->can_manage_users) {
            $validPermissions[] = 'users.manage';
        }
        $user->syncPermissions($validPermissions);

        // Sync outlet access
        if ($this->allOutlets) {
            $allOutletIds = Outlet::where('company_id', $companyId)->pluck('id')->toArray();
            $user->outlets()->sync($allOutletIds);
        } else {
            $user->outlets()->sync(array_map('intval', $this->outletIds));
        }

        $this->closeModal();
    }

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

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser->isSystemRole();

        $query = User::with('outlets')->when($this->search, fn ($q) =>
            $q->where('name', 'like', '%'.$this->search.'%')
              ->orWhere('email', 'like', '%'.$this->search.'%')
        );

        if (! $isSuperAdmin) {
            $query->where('company_id', $currentUser->company_id);
            $query->whereDoesntHave('roles', fn ($q) =>
                $q->whereIn('name', ['Super Admin', 'System Admin'])
            );
        }

        $users     = $query->orderBy('name')->paginate(20);
        $companies = $isSuperAdmin ? Company::orderBy('name')->get() : collect();
        $outlets   = Outlet::when(! $isSuperAdmin, fn ($q) =>
            $q->where('company_id', $currentUser->company_id)
        )->where('is_active', true)->orderBy('name')->get();

        $modules = self::MODULES;

        return view('livewire.settings.users', compact(
            'users', 'companies', 'outlets', 'isSuperAdmin', 'modules'
        ))->layout('layouts.app', ['title' => 'Users']);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->designation = '';
        $this->company_id = null;
        $this->moduleAccess = [];
        $this->allOutlets = false;
        $this->outletIds = [];
        $this->can_manage_users = false;
        $this->can_approve_po = false;
        $this->can_approve_pr = false;
        $this->can_delete_records = false;
        $this->can_view_all_outlets = false;
        $this->resetValidation();
    }
}
