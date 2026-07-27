<?php

namespace App\Livewire\Settings;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    // Access level: an assignable role name, or 'custom' for a hand-picked set
    public string $accessRole = 'custom';

    // Available modules (permission name => display label)
    public const MODULES = [
        'ingredients.view'     => 'Ingredients',
        'recipes.view'         => 'Recipes',
        'purchasing.view'      => 'Purchasing',
        'sales.view'           => 'Sales',
        'inventory.view'       => 'Inventory & Kitchen',
        'hr.view'              => 'HR — Employees & Labour',
        'hr.attendance'        => 'HR — Attendance & Service Charge',
        'hr.claims'            => 'HR — Overtime Claims',
        'hr.documents.view'    => 'HR Documents (View)',
        'hr.documents.manage'  => 'HR Documents (Manage)',
        'roster.create'        => 'Duty Roster (Create)',
        'roster.edit'          => 'Duty Roster (Edit/Submit)',
        'roster.approve'       => 'Duty Roster (Approve)',
        'roster.delete'        => 'Duty Roster (Delete)',
        'roster.amend'         => 'Duty Roster (Amend Approved)',
        'roster.settings'      => 'Duty Roster (Settings)',
        'reports.view'         => 'Reports',
        'audit.view'           => 'Audit Logs',
        'settings.view'        => 'Settings',
    ];

    /**
     * Roles a company admin may assign, with a plain-language description.
     * The module set each role grants comes from role_has_permissions (the
     * single source of truth) — shown locked in the modal so what a role
     * includes is always explicit. System roles are deliberately absent:
     * they can never be granted from this screen.
     */
    public const ASSIGNABLE_ROLES = [
        'Company Admin'      => 'Full access: every module, all settings, billing and user management.',
        'Business Manager'   => 'Runs the business day-to-day: all operational modules, reports, rosters, billing and user management.',
        'Operations Manager' => 'Operational oversight: purchasing, inventory, recipes, sales, reports and audit logs.',
        'Branch Manager'     => 'Runs outlets: sales, purchasing, inventory, reports and audit logs.',
        'Outlet Manager'     => 'Duty roster planning: create and edit rosters for their outlets.',
        'Chef'               => 'Kitchen-focused: recipes, ingredients, inventory and purchasing.',
        'Purchasing'         => 'Creates and processes purchase orders.',
        'Finance'            => 'Reviews the numbers: sales, purchasing, inventory and reports.',
        'HR Manager'         => 'People operations: employees, HR documents and duty rosters.',
        'Staff'              => 'No modules by default — add only what this person needs.',
    ];

    /** Capability flags suggested when a role is picked (all editable after). */
    public const ROLE_CAPABILITIES = [
        'Company Admin'      => ['can_manage_users', 'can_approve_po', 'can_approve_pr', 'can_delete_records', 'can_view_all_outlets', 'can_receive_grn', 'can_manage_invoices'],
        'Business Manager'   => ['can_manage_users', 'can_approve_po', 'can_approve_pr', 'can_view_all_outlets', 'can_manage_invoices'],
        'Operations Manager' => ['can_view_all_outlets', 'can_approve_pr'],
        'Branch Manager'     => ['can_receive_grn'],
        'Outlet Manager'     => [],
        'Chef'               => [],
        'Purchasing'         => [],
        'Finance'            => ['can_manage_invoices'],
        'HR Manager'         => [],
        'Staff'              => [],
    ];

    public function updatedSearch(): void { $this->resetPage(); }

    /** Module permissions each assignable role grants (from the roles table). */
    protected function rolePermMap(): array
    {
        static $map = null;
        return $map ??= DB::table('role_has_permissions')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('roles.name', array_keys(self::ASSIGNABLE_ROLES))
            ->select('roles.name as role_name', 'permissions.name as perm')
            ->get()
            ->groupBy('role_name')
            ->map(fn ($rows) => $rows->pluck('perm')->values()->all())
            ->all();
    }

    /**
     * Picking an access level applies that role's template: its module set
     * (rendered locked in the modal) plus suggested capability flags. Both
     * remain editable — switching to Custom keeps the ticks for fine-tuning.
     */
    public function updatedAccessRole(string $value): void
    {
        if ($value === 'custom' || ! isset(self::ASSIGNABLE_ROLES[$value])) {
            return;
        }

        $rolePerms = $this->rolePermMap()[$value] ?? [];
        $this->moduleAccess = array_values(array_intersect($rolePerms, array_keys(self::MODULES)));

        $suggested = self::ROLE_CAPABILITIES[$value] ?? [];
        foreach (array_keys($this->capabilityFlags()) as $flag) {
            $this->{$flag} = in_array($flag, $suggested, true);
        }
        // users.manage rides on the role where defined — mirror the flag.
        if (in_array('users.manage', $rolePerms, true)) {
            $this->can_manage_users = true;
        }
    }

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

        // Capabilities are per-company: show this company's pivot flags
        // (fall back to the users-table cache for legacy rows).
        $contextCompanyId = $currentUser->isSystemRole()
            ? (int) $user->company_id
            : (int) $currentUser->company_id;
        $flags = $contextCompanyId ? $user->capabilitiesForCompany($contextCompanyId) : null;

        $this->can_manage_users    = $flags['can_manage_users']     ?? $user->can_manage_users;
        $this->can_approve_po      = $flags['can_approve_po']       ?? $user->can_approve_po;
        $this->can_approve_pr      = $flags['can_approve_pr']       ?? $user->can_approve_pr;
        $this->can_delete_records  = $flags['can_delete_records']   ?? $user->can_delete_records;
        $this->can_view_all_outlets = $flags['can_view_all_outlets'] ?? $user->can_view_all_outlets;
        $this->can_receive_grn     = $flags['can_receive_grn']      ?? $user->can_receive_grn;
        $this->can_manage_invoices = $flags['can_manage_invoices']  ?? $user->can_manage_invoices;

        // Access level: the user's assignable role in this company (teams
        // pivot), or Custom when they have none / only a system role.
        $roleName = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.team_id', $contextCompanyId)
            ->whereIn('roles.name', array_keys(self::ASSIGNABLE_ROLES))
            ->value('roles.name');
        $this->accessRole = $roleName ?: 'custom';

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

        // Kitchen mode — derived from the kitchen_users pivot, which is what the
        // CK nav button, workspace switcher, and kitchen.user middleware gate on.
        $allKitchenIds  = \App\Models\CentralKitchen::where('company_id', $user->company_id)
            ->pluck('id')->map(fn ($i) => (int) $i)->all();
        $userKitchenIds = DB::table('kitchen_users')
            ->where('user_id', $user->id)
            ->whereIn('kitchen_id', $allKitchenIds)
            ->pluck('kitchen_id')->map(fn ($i) => (int) $i)->all();

        if (empty($allKitchenIds) || empty($userKitchenIds)) {
            $this->kitchenMode = 'none';
            $this->kitchenIds = [];
        } elseif (count($userKitchenIds) >= count($allKitchenIds)) {
            $this->kitchenMode = 'all';
            $this->kitchenIds = [];
        } else {
            $this->kitchenMode = 'selected';
            $this->kitchenIds = array_map('strval', $userKitchenIds);
        }

        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'name'        => 'required|string|max:100',
            // On create an existing email is not an error — it links that user
            // to this company instead (multi-company access, one login).
            'email'       => $this->editingId
                ? ['required', 'email', Rule::unique('users', 'email')->ignore($this->editingId)]
                : ['required', 'email'],
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

        if (! $this->editingId && $companyId) {
            $existing = User::where('email', $this->email)->first();
            if ($existing) {
                $this->linkExistingUser($existing, (int) $companyId);
                return;
            }
        }

        $primaryOutletId = ! empty($this->outletIds) ? (int) $this->outletIds[0] : null;

        $data = [
            'name'                 => $this->name,
            'email'                => $this->email,
            'company_id'           => $companyId,
            'outlet_id'            => $primaryOutletId,
            'designation'          => $this->designation ?: null,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $user->update($data);
            session()->flash('success', 'User updated.');
        } else {
            $user = User::create($data);
            // Not mass-assignable — admin-created accounts are pre-verified.
            $user->forceFill(['email_verified_at' => now()])->save();
            session()->flash('success', 'User created.');
        }

        // Keep company membership (company_user) in step with the active company.
        if ($companyId) {
            $user->companies()->syncWithoutDetaching([(int) $companyId]);

            // Capability flags live on the pivot, per company
            $user->setCapabilitiesForCompany((int) $companyId, $this->capabilityFlags());
        }

        if ($companyId) {
            $this->syncAccessLevel($user, (int) $companyId);
            $this->syncOutletAccess($user, (int) $companyId);
        }

        session()->flash('success', ($this->editingId ? 'User updated.' : 'User created.'));
        $this->redirect(route('settings.users'), navigate: true);
    }

    /**
     * Link an already-registered user (same email, another company) to the
     * current company, so one login can manage multiple companies.
     */
    private function linkExistingUser(User $user, int $companyId): void
    {
        if ($user->isSystemRole()) {
            $this->addError('email', 'This email belongs to a system account and cannot be linked.');
            return;
        }

        $alreadyMember = (int) $user->company_id === $companyId
            || $user->companies()->where('companies.id', $companyId)->exists();

        if ($alreadyMember) {
            $this->addError('email', 'A user with this email already belongs to this company.');
            return;
        }

        $user->companies()->syncWithoutDetaching([$companyId]);
        // Make sure their original company is recorded as a membership too
        // (backfill covers existing users; this is belt-and-braces).
        if ($user->company_id) {
            $user->companies()->syncWithoutDetaching([(int) $user->company_id]);
        }

        // Role + module permissions are per-company (Spatie teams mode) —
        // synced under the company being linked into.
        $this->syncAccessLevel($user, $companyId);

        // Capability flags are per-company too: exactly what was ticked, on
        // this company's pivot only — their other companies are untouched.
        $user->setCapabilitiesForCompany($companyId, $this->capabilityFlags());

        $this->syncOutletAccess($user, $companyId);

        session()->flash('success', 'Existing user "' . $user->name . '" linked to this company. They can now switch companies from the sidebar — their password stays the same.');
        $this->redirect(route('settings.users'), navigate: true);
    }

    /**
     * Apply the chosen access level for ONE company: the assignable role
     * (teams-scoped, so other companies' assignments survive) plus direct
     * module permissions. Role-granted modules are always merged into the
     * direct set so ticked access can never silently fall below the role's
     * baseline; system roles are never assigned or removed here.
     */
    private function syncAccessLevel(User $user, int $companyId): void
    {
        $prevTeam = getPermissionsTeamId();
        setPermissionsTeamId($companyId);

        try {
            $valid = array_intersect($this->moduleAccess, array_keys(self::MODULES));

            if (isset(self::ASSIGNABLE_ROLES[$this->accessRole])) {
                $rolePerms = $this->rolePermMap()[$this->accessRole] ?? [];
                $valid = array_merge($valid, array_intersect($rolePerms, array_keys(self::MODULES)));
                if (! $user->isSystemRole()) {
                    $user->unsetRelation('roles');
                    $user->syncRoles([$this->accessRole]);
                }
            } elseif (! $user->isSystemRole()) {
                // Custom: access is exactly the ticked set, no role attached.
                $user->unsetRelation('roles');
                $user->syncRoles([]);
            }

            if ($this->can_manage_users) {
                $valid[] = 'users.manage';
            }
            $user->unsetRelation('permissions');
            $user->syncPermissions(array_values(array_unique($valid)));
        } finally {
            setPermissionsTeamId($prevTeam);
        }

        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    /** The modal's capability checkboxes as a flags array. */
    private function capabilityFlags(): array
    {
        return [
            'can_manage_users'     => $this->can_manage_users,
            'can_approve_po'       => $this->can_approve_po,
            'can_approve_pr'       => $this->can_approve_pr,
            'can_delete_records'   => $this->can_delete_records,
            'can_view_all_outlets' => $this->can_view_all_outlets,
            'can_receive_grn'      => $this->can_receive_grn,
            'can_manage_invoices'  => $this->can_manage_invoices,
        ];
    }

    /**
     * Apply the modal's outlet/kitchen access selection for ONE company.
     * Scoped so a multi-company user's assignments in other companies survive.
     */
    private function syncOutletAccess(User $user, int $companyId): void
    {
        // Resolve outlet IDs from mode
        $allRegularOutletIds = Outlet::where('company_id', $companyId)->where('is_active', true)->pluck('id')->toArray();
        $kitchenOutletIds = \App\Models\CentralKitchen::where('company_id', $companyId)->whereNotNull('outlet_id')->pluck('outlet_id')->toArray();
        $regularOutletIds = array_diff($allRegularOutletIds, $kitchenOutletIds);

        $syncIds = [];

        // Regular outlets
        if ($this->outletMode === 'all') {
            $syncIds = array_merge($syncIds, $regularOutletIds);
            $user->setCapabilitiesForCompany($companyId, ['can_view_all_outlets' => true]);
        } else {
            // Clear "all outlets" flag when switching to selected or except mode
            $user->setCapabilitiesForCompany($companyId, ['can_view_all_outlets' => false]);

            if ($this->outletMode === 'all_except') {
                $excludeIds = array_map('intval', $this->outletIds);
                $syncIds = array_merge($syncIds, array_diff($regularOutletIds, $excludeIds));
            } elseif ($this->outletMode === 'selected') {
                $syncIds = array_merge($syncIds, array_map('intval', $this->outletIds));
            }
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

        // Sync only THIS company's outlets: detach the ones not selected,
        // attach the selected — rows for other companies are left untouched.
        $companyOutletIds = Outlet::where('company_id', $companyId)->pluck('id')->toArray();
        $syncIds = array_unique($syncIds);
        $removeOutletIds = array_diff($companyOutletIds, $syncIds);
        if (! empty($removeOutletIds)) {
            $user->outlets()->detach($removeOutletIds);
        }
        $user->outlets()->syncWithoutDetaching($syncIds);

        // Sync kitchen-mode access. The "Switch to Central Kitchen Mode" nav
        // button, the workspace switcher, and the kitchen.user middleware all
        // gate on the kitchen_users pivot — outlet rows alone leave the CK
        // navigation invisible.
        $targetKitchenIds = match ($this->kitchenMode) {
            'all'        => \App\Models\CentralKitchen::where('company_id', $companyId)
                ->pluck('id')->map(fn ($i) => (int) $i)->all(),
            'all_except' => \App\Models\CentralKitchen::where('company_id', $companyId)
                ->whereNotIn('id', array_map('intval', $this->kitchenIds))
                ->pluck('id')->map(fn ($i) => (int) $i)->all(),
            'selected'   => \App\Models\CentralKitchen::where('company_id', $companyId)
                ->whereIn('id', array_map('intval', $this->kitchenIds))
                ->pluck('id')->map(fn ($i) => (int) $i)->all(),
            default      => [],
        };

        // Only this company's kitchen rows are compared/removed
        $currentKitchenIds = DB::table('kitchen_users')
            ->join('central_kitchens', 'central_kitchens.id', '=', 'kitchen_users.kitchen_id')
            ->where('kitchen_users.user_id', $user->id)
            ->where('central_kitchens.company_id', $companyId)
            ->pluck('kitchen_users.kitchen_id')->map(fn ($i) => (int) $i)->all();

        foreach (array_diff($targetKitchenIds, $currentKitchenIds) as $kitchenId) {
            DB::table('kitchen_users')->insert([
                'kitchen_id' => $kitchenId,
                'user_id'    => $user->id,
                'role'       => 'staff',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $removeKitchenIds = array_diff($currentKitchenIds, $targetKitchenIds);
        if (! empty($removeKitchenIds)) {
            DB::table('kitchen_users')
                ->where('user_id', $user->id)
                ->whereIn('kitchen_id', $removeKitchenIds)
                ->delete();
        }

        // Default kitchen: first granted kitchen. Only overwrite when the
        // current default is empty or belongs to this company — a linked user's
        // default in another company is not ours to clobber.
        $currentDefaultInCompany = $user->default_kitchen_id
            && \App\Models\CentralKitchen::where('id', $user->default_kitchen_id)
                ->where('company_id', $companyId)->exists();

        if (! $user->default_kitchen_id || $currentDefaultInCompany) {
            $user->update(['default_kitchen_id' => $targetKitchenIds[0] ?? null]);
        }
    }

    public function delete(int $id): void
    {
        if ($id === Auth::id()) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }

        $user = User::findOrFail($id);
        $currentUser = Auth::user();

        if (! $currentUser->isSystemRole() && $user->isSystemRole()) {
            session()->flash('error', 'You cannot delete this user.');
            return;
        }

        $scopeCompanyId = $currentUser->isSystemRole()
            ? (int) $user->company_id
            : (int) $currentUser->company_id;

        $otherCompanyIds = $user->companies()
            ->where('companies.id', '!=', $scopeCompanyId)
            ->pluck('companies.id');

        // Member of other companies too → unlink from this one, keep the account
        if ($scopeCompanyId && $otherCompanyIds->isNotEmpty()) {
            $user->companies()->detach($scopeCompanyId);

            $companyOutletIds = Outlet::where('company_id', $scopeCompanyId)->pluck('id')->all();
            if (! empty($companyOutletIds)) {
                $user->outlets()->detach($companyOutletIds);
            }

            $companyKitchenIds = \App\Models\CentralKitchen::where('company_id', $scopeCompanyId)->pluck('id')->all();
            if (! empty($companyKitchenIds)) {
                DB::table('kitchen_users')
                    ->where('user_id', $user->id)
                    ->whereIn('kitchen_id', $companyKitchenIds)
                    ->delete();
            }

            // Drop their role/permission rows for this company (teams mode)
            DB::table('model_has_roles')
                ->where('model_type', User::class)->where('model_id', $user->id)
                ->where('team_id', $scopeCompanyId)->delete();
            DB::table('model_has_permissions')
                ->where('model_type', User::class)->where('model_id', $user->id)
                ->where('team_id', $scopeCompanyId)->delete();

            // If this was their active company, move them to a remaining one
            if ((int) $user->company_id === $scopeCompanyId) {
                $user->update(['company_id' => $otherCompanyIds->first(), 'outlet_id' => null]);
            }

            // Users-table capability columns cache the active company's flags
            $user->refreshCapabilityCache();

            session()->flash('success', 'User removed from this company. Their account and access to other companies remain.');
            return;
        }

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

        $query = User::with(['outlets', 'company', 'roles'])
            // Pivot capabilities for THIS company (list badge) — superadmin
            // sees the global list, where the cache columns are close enough
            ->when(! $isSuperAdmin, fn ($q) =>
                $q->with(['companies' => fn ($c) => $c->where('companies.id', $currentUser->company_id)])
            )
            ->addSelect(['*',
                // last_active_at heartbeat first; DB sessions as legacy
                // fallback (prod sessions are on Redis). 0 renders as Never.
                \Illuminate\Support\Facades\DB::raw('GREATEST(COALESCE((SELECT MAX(last_activity) FROM sessions WHERE sessions.user_id = users.id), 0), COALESCE(UNIX_TIMESTAMP(users.last_active_at), 0)) as last_session_activity'),
            ])
            ->when($this->search, fn ($q) =>
                // Grouped so the OR can't bypass the company filter below
                $q->where(fn ($qq) => $qq
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%'))
            );

        if (! $isSuperAdmin) {
            // Members of this company — whether or not it's their active one
            $companyId = $currentUser->company_id;
            $query->where(fn ($q) => $q
                ->where('company_id', $companyId)
                ->orWhereHas('companies', fn ($c) => $c->where('companies.id', $companyId)));
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

        // Only offer roles that actually exist in this install. Display name
        // and description come from the roles table (editable in Admin > Role
        // Templates); the const description is the fallback for legacy rows.
        $roleRows = DB::table('roles')
            ->whereIn('name', array_keys(self::ASSIGNABLE_ROLES))
            ->get(['name', 'display_name', 'description']);
        $assignableRoles = [];
        $roleDisplayMap  = [];
        foreach (array_keys(self::ASSIGNABLE_ROLES) as $name) {
            $row = $roleRows->firstWhere('name', $name);
            if (! $row) continue;
            $assignableRoles[$name] = $row->description ?: self::ASSIGNABLE_ROLES[$name];
            $roleDisplayMap[$name]  = $row->display_name ?: $name;
        }
        $rolePermMap = $this->rolePermMap();

        return view('livewire.settings.users', compact(
            'users', 'companies', 'outlets', 'regularOutlets', 'kitchens', 'isSuperAdmin', 'modules',
            'assignableRoles', 'rolePermMap', 'roleDisplayMap'
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
        $this->accessRole = 'custom';
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
