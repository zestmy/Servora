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

    // Outlet access
    public string  $outletMode = 'all'; // all, all_except, selected
    public array   $outletIds  = [];

    // Kitchen access
    public string  $kitchenMode = 'none'; // none, all, all_except, selected
    public array   $kitchenIds  = [];

    // Capabilities
    public bool    $can_manage_users    = false;
    public bool    $can_approve_po      = false;
    public bool    $can_approve_pr      = false;
    public bool    $can_delete_records  = false;
    public bool    $can_view_all_outlets = false;
    public bool    $can_receive_grn     = false;
    public bool    $can_manage_invoices = false;

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
        $this->can_receive_grn     = $user->can_receive_grn;
        $this->can_manage_invoices = $user->can_manage_invoices;

        // Load current permissions as module access
        $this->moduleAccess = $user->getDirectPermissions()->pluck('name')->toArray();
        // Also include role-based permissions for display
        $allPerms = $user->getAllPermissions()->pluck('name')->toArray();
        $this->moduleAccess = array_values(array_unique(array_merge($this->moduleAccess, $allPerms)));

        // Outlet access — determine mode
        $assignedOutletIds = $user->outlets->pluck('id')->toArray();
        $allOutletIds = Outlet::where('company_id', $user->company_id)->where('is_active', true)->pluck('id')->toArray();
        // Separate kitchen outlets from regular outlets
        $kitchenOutletIds = \App\Models\CentralKitchen::where('company_id', $user->company_id)->whereNotNull('outlet_id')->pluck('outlet_id')->toArray();
        $regularOutletIds = array_diff($allOutletIds, $kitchenOutletIds);

        $assignedRegular = array_intersect($assignedOutletIds, $regularOutletIds);
        $assignedKitchens = array_intersect($assignedOutletIds, $kitchenOutletIds);

        // Outlet mode
        if (count($assignedRegular) >= count($regularOutletIds) && count($regularOutletIds) > 0) {
            $this->outletMode = 'all';
            $this->outletIds = [];
        } elseif (count($assignedRegular) > count($regularOutletIds) / 2) {
            $this->outletMode = 'all_except';
            $this->outletIds = array_map('strval', array_values(array_diff($regularOutletIds, $assignedRegular)));
        } else {
            $this->outletMode = 'selected';
            $this->outletIds = array_map('strval', array_values($assignedRegular));
        }

        // Kitchen mode
        if (empty($kitchenOutletIds) || empty($assignedKitchens)) {
            $this->kitchenMode = 'none';
            $this->kitchenIds = [];
        } elseif (count($assignedKitchens) >= count($kitchenOutletIds)) {
            $this->kitchenMode = 'all';
            $this->kitchenIds = [];
        } else {
            $this->kitchenMode = 'selected';
            // Map outlet_ids back to kitchen ids
            $this->kitchenIds = array_map('strval', \App\Models\CentralKitchen::whereIn('outlet_id', $assignedKitchens)->pluck('id')->toArray());
        }

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
            'can_receive_grn'      => $this->can_receive_grn,
            'can_manage_invoices'  => $this->can_manage_invoices,
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

        // Resolve outlet IDs from mode
        $allRegularOutletIds = Outlet::where('company_id', $companyId)->where('is_active', true)->pluck('id')->toArray();
        $kitchenOutletIds = \App\Models\CentralKitchen::where('company_id', $companyId)->whereNotNull('outlet_id')->pluck('outlet_id')->toArray();
        $regularOutletIds = array_diff($allRegularOutletIds, $kitchenOutletIds);

        $syncIds = [];

        // Regular outlets
        if ($this->outletMode === 'all') {
            $syncIds = array_merge($syncIds, $regularOutletIds);
            $this->can_view_all_outlets = true;
            $user->update(['can_view_all_outlets' => true]);
        } elseif ($this->outletMode === 'all_except') {
            $excludeIds = array_map('intval', $this->outletIds);
            $syncIds = array_merge($syncIds, array_diff($regularOutletIds, $excludeIds));
        } elseif ($this->outletMode === 'selected') {
            $syncIds = array_merge($syncIds, array_map('intval', $this->outletIds));
        }

        // Central kitchens
        if ($this->kitchenMode === 'all') {
            $syncIds = array_merge($syncIds, $kitchenOutletIds);
        } elseif ($this->kitchenMode === 'all_except') {
            $excludeKitchenIds = array_map('intval', $this->kitchenIds);
            $excludeOutletIds = \App\Models\CentralKitchen::whereIn('id', $excludeKitchenIds)->whereNotNull('outlet_id')->pluck('outlet_id')->toArray();
            $syncIds = array_merge($syncIds, array_diff($kitchenOutletIds, $excludeOutletIds));
        } elseif ($this->kitchenMode === 'selected') {
            $selectedKitchenOutletIds = \App\Models\CentralKitchen::whereIn('id', array_map('intval', $this->kitchenIds))->whereNotNull('outlet_id')->pluck('outlet_id')->toArray();
            $syncIds = array_merge($syncIds, $selectedKitchenOutletIds);
        }

        $user->outlets()->sync(array_unique($syncIds));

        // Set default_kitchen_id if kitchen access granted
        if (in_array($this->kitchenMode, ['all', 'all_except', 'selected'])) {
            $firstKitchen = \App\Models\CentralKitchen::where('company_id', $companyId)->first();
            $user->update(['default_kitchen_id' => $firstKitchen?->id]);
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

        // Separate regular outlets from kitchen outlets
        $kitchenOutletIds = \App\Models\CentralKitchen::when(! $isSuperAdmin, fn ($q) =>
            $q->where('company_id', $currentUser->company_id)
        )->whereNotNull('outlet_id')->pluck('outlet_id')->toArray();
        $regularOutlets = $outlets->reject(fn ($o) => in_array($o->id, $kitchenOutletIds));
        $kitchens = \App\Models\CentralKitchen::when(! $isSuperAdmin, fn ($q) =>
            $q->where('company_id', $currentUser->company_id)
        )->where('is_active', true)->orderBy('name')->get();

        return view('livewire.settings.users', compact(
            'users', 'companies', 'outlets', 'regularOutlets', 'kitchens', 'isSuperAdmin', 'modules'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Users']);
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
        $this->outletMode = 'all';
        $this->outletIds = [];
        $this->kitchenMode = 'none';
        $this->kitchenIds = [];
        $this->can_manage_users = false;
        $this->can_approve_po = false;
        $this->can_approve_pr = false;
        $this->can_delete_records = false;
        $this->can_view_all_outlets = false;
        $this->can_receive_grn = false;
        $this->can_manage_invoices = false;
        $this->resetValidation();
    }
}
