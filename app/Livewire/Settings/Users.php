<?php

namespace App\Livewire\Settings;

use App\Helpers\PermissionRegistry;
use App\Helpers\RoleCatalogue;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use Carbon\Carbon;
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

    /**
     * The last capability flag. The other six (manage users, approve PO/PR, receive GRN,
     * manage invoices, delete records) became ordinary permissions in Phase 1 and are now
     * ticked in the module grid above. This one stays because it is not a capability at
     * all — it says which outlets the user's abilities apply to, so it belongs with the
     * outlet picker it sits next to.
     */
    public bool    $can_view_all_outlets = false;

    public string  $search     = '';

    // Access level: an assignable role name, or 'custom' for a hand-picked set
    public string $accessRole = 'custom';

    /**
     * Grantable modules (permission name => display label).
     *
     * This was a hand-maintained 23-entry const, and it had quietly fallen nine
     * permissions behind the `permissions` table — `hr.payroll`, `hr.leave`, the
     * label abilities and more were enforced but appeared in no admin screen, so
     * the only way to change who could run payroll was to write a migration.
     * It now derives from config/permissions.php, and PermissionRegistryTest
     * fails the build if the two ever diverge again.
     *
     * Capability-managed abilities (`users.manage`) are excluded, exactly as
     * before: it is granted by the "Can manage users" checkbox below, and two
     * controls writing one permission could disagree.
     *
     * @return array<string, string>
     */
    public static function modules(): array
    {
        return PermissionRegistry::titles();
    }

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

    /**
     * Abilities suggested when a role is picked, on top of the role's own permission set.
     *
     * These were capability flags until Phase 1; they are permissions now, so they are
     * suggested into $moduleAccess rather than into a separate row of checkboxes. They
     * stay a suggestion rather than moving onto the roles themselves because the flags
     * were always per-user: baking them into role_has_permissions would hand PO approval
     * to every Company Admin who has it unticked today.
     */
    public const ROLE_SUGGESTED_ABILITIES = [
        'Company Admin'      => ['users.manage', 'purchasing.approve', 'purchasing.request', 'purchasing.receive', 'purchasing.invoice', 'purchasing.delete', 'sales.delete', 'inventory.delete', 'hr.clock.delete', 'hr.claims.delete'],
        'Business Manager'   => ['users.manage', 'purchasing.approve', 'purchasing.request', 'purchasing.invoice'],
        'Operations Manager' => ['purchasing.request'],
        'Branch Manager'     => ['purchasing.receive'],
        'Outlet Manager'     => [],
        'Chef'               => [],
        'Purchasing'         => [],
        'Finance'            => ['purchasing.invoice'],
        'HR Manager'         => [],
        'Staff'              => [],
    ];

    /**
     * Roles whose suggestion set includes "see every outlet". Outlet scope is not a
     * permission, so it cannot ride in the list above.
     */
    public const ROLE_SUGGESTS_ALL_OUTLETS = [
        'Company Admin', 'Business Manager', 'Operations Manager',
    ];

    public function updatedSearch(): void { $this->resetPage(); }

    /** The company whose roles and access this screen is editing. */
    protected function contextCompanyId(): int
    {
        $current = Auth::user();

        return (int) ($current->isSystemRole()
            ? ($this->company_id ?: $current->company_id)
            : $current->company_id);
    }

    /**
     * Roles assignable here: the system presets plus this company's own.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function assignableRoles()
    {
        return RoleCatalogue::forCompany($this->contextCompanyId());
    }

    /**
     * Module permissions each assignable role grants, keyed by role ID.
     *
     * Keyed by ID rather than name because a company may create its own "Chef" beside the
     * global preset — the unique index is (team_id, name, guard_name), so both can exist.
     * The previous version grouped by role NAME, which would have silently merged the two
     * roles' permission sets into one.
     *
     * @return array<int, list<string>>
     */
    protected function rolePermMap(): array
    {
        return RoleCatalogue::permissionsByRoleId($this->assignableRoles()->pluck('id'));
    }

    /** The chosen role row, or null when the access level is Custom. */
    protected function selectedRole(): ?object
    {
        if ($this->accessRole === 'custom') {
            return null;
        }

        return $this->assignableRoles()->firstWhere('id', (int) $this->accessRole);
    }

    /**
     * Picking an access level pre-fills the grid with what that role grants, plus the
     * abilities that used to be capability flags. Everything stays editable — including
     * unticking one of the role's own abilities, which becomes a denial on save.
     */
    public function updatedAccessRole(string $value): void
    {
        $role = $this->selectedRole();

        if (! $role) {
            return;
        }

        $grantable = array_keys(self::modules());
        $rolePerms = $this->rolePermMap()[$role->id] ?? [];

        // Suggestions are keyed by preset name; a company's own role has no suggestions.
        $suggested = $role->is_preset
            ? (self::ROLE_SUGGESTED_ABILITIES[$role->name] ?? [])
            : [];

        $this->moduleAccess = array_values(array_unique(array_intersect(
            array_merge($rolePerms, $suggested),
            $grantable
        )));

        $this->can_view_all_outlets = $role->is_preset
            && in_array($role->name, self::ROLE_SUGGESTS_ALL_OUTLETS, true);

        if ($this->can_view_all_outlets) {
            $this->outletMode = 'all';
            $this->outletIds  = [];
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

        $this->can_view_all_outlets = $flags['can_view_all_outlets'] ?? $user->can_view_all_outlets;

        // Access level: the user's role in this company (teams pivot), by ID — a company
        // may have its own role sharing a preset's name, so the name is not an identity.
        $role = RoleCatalogue::roleFor($user->id, $contextCompanyId);
        $this->accessRole = $role ? (string) $role->id : 'custom';

        // The grid shows EFFECTIVE access: (role ∪ granted) − denied. Ticking is
        // therefore "what this person can do", and save() works out afterwards which
        // side of the role each tick falls on. Scoped to the company being edited —
        // a system admin editing someone in another company must not read the
        // permissions of their own.
        $prevTeam = getPermissionsTeamId();
        setPermissionsTeamId($contextCompanyId);

        try {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $effective = $user->getAllPermissions()->pluck('name')->all();
            $denied    = $user->deniedPermissions();
        } finally {
            setPermissionsTeamId($prevTeam);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }

        $this->moduleAccess = array_values(array_diff(array_unique($effective), $denied));

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
     * Apply the ticked access for ONE company, as `(role ∪ granted) − denied`.
     *
     * $moduleAccess is EFFECTIVE access — what this person should be able to do — so the
     * split is derived here rather than asked of the admin:
     *   granted = ticked, minus whatever the role already gives  (genuine extras)
     *   denied  = the role's abilities that were UNticked        ("this role, but not X")
     *
     * Before Phase 3 the role's permissions were merged into the user's direct grants on
     * every save. That made a role edit one-way: removing a module from a role left every
     * existing holder with their own copy of it, so the Role Templates screen's promise
     * that a change "applies to every user holding this role" was only true for additions.
     * Nothing is copied now — the role is the source, and editing it reaches its holders.
     *
     * Teams-scoped throughout, so a multi-company user's other companies survive; system
     * roles are never assigned or removed here.
     */
    private function syncAccessLevel(User $user, int $companyId): void
    {
        $prevTeam = getPermissionsTeamId();
        setPermissionsTeamId($companyId);

        try {
            $grantable = array_keys(self::modules());
            $wanted    = array_values(array_intersect($this->moduleAccess, $grantable));
            $rolePerms = [];
            $role      = $this->selectedRole();

            if ($role) {
                $rolePerms = array_values(array_intersect(
                    $this->rolePermMap()[$role->id] ?? [],
                    $grantable
                ));
                if (! $user->isSystemRole()) {
                    $user->unsetRelation('roles');
                    // By model, not name: syncRoles('Chef') would resolve through
                    // Role::findByName()->first(), which cannot tell a company's own
                    // "Chef" from the global preset.
                    $user->syncRoles([\Spatie\Permission\Models\Role::findById($role->id)]);
                }
            } elseif (! $user->isSystemRole()) {
                // Custom: access is exactly the ticked set, no role attached.
                $user->unsetRelation('roles');
                $user->syncRoles([]);
            }

            $granted = array_values(array_diff($wanted, $rolePerms));
            $denied  = array_values(array_diff($rolePerms, $wanted));

            $user->unsetRelation('permissions');
            $user->syncPermissions($granted);
            $user->syncDenials($companyId, $denied);
        } finally {
            setPermissionsTeamId($prevTeam);
        }

        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    /**
     * The modal's remaining flag. The other six became permissions in Phase 1; their
     * pivot columns are left in place but are no longer written or read, and are dropped
     * in a later phase once nothing has referenced them for a while.
     */
    private function capabilityFlags(): array
    {
        return ['can_view_all_outlets' => $this->can_view_all_outlets];
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
                // Legacy DB-session activity (prod sessions moved to Redis, so
                // this is empty for anything recent). A real unix timestamp,
                // therefore timezone-safe to read in SQL.
                //
                // users.last_active_at is deliberately NOT converted here:
                // it's a naive DATETIME holding an app-timezone wall clock, and
                // UNIX_TIMESTAMP() would reinterpret it in MySQL's own timezone
                // (UTC on the VPS), landing every heartbeat 8 hours in the
                // future — "7 hours from now". It's merged below in PHP,
                // through the Eloquent datetime cast.
                DB::raw('(SELECT MAX(last_activity) FROM sessions WHERE sessions.user_id = users.id) as last_session_activity'),
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

        // Last activity per visible user, resolved in PHP so both sources land
        // on the same basis: the heartbeat through the datetime cast, the
        // legacy session through its unix timestamp. Mirrors Admin\Users.
        $appTz      = config('app.timezone');
        $lastActive = [];
        foreach ($users->items() as $u) {
            $candidates = [];
            if ($u->last_session_activity) {
                $candidates[] = Carbon::createFromTimestamp($u->last_session_activity, $appTz);
            }
            if ($u->last_active_at) {
                $candidates[] = $u->last_active_at->copy()->setTimezone($appTz);
            }
            $best = null;
            foreach ($candidates as $c) {
                if (! $best || $c->gt($best)) $best = $c;
            }
            if ($best) $lastActive[$u->id] = $best;
        }

        $companies = $isSuperAdmin ? Company::orderBy('name')->get() : collect();
        $outlets   = Outlet::when(! $isSuperAdmin, fn ($q) =>
            $q->where('company_id', $currentUser->company_id)
        )->where('is_active', true)->orderBy('name')->get();

        $modules = self::modules();
        // Same abilities as $modules, but grouped by module for the checkbox grid:
        // 33 flat checkboxes is a wall, 4 groups of related ones is a form.
        $moduleGrid = PermissionRegistry::grid();

        // Separate regular outlets from kitchen outlets
        $kitchenOutletIds = \App\Models\CentralKitchen::when(! $isSuperAdmin, fn ($q) =>
            $q->where('company_id', $currentUser->company_id)
        )->whereNotNull('outlet_id')->pluck('outlet_id')->toArray();
        $regularOutlets = $outlets->reject(fn ($o) => in_array($o->id, $kitchenOutletIds));
        $kitchens = \App\Models\CentralKitchen::when(! $isSuperAdmin, fn ($q) =>
            $q->where('company_id', $currentUser->company_id)
        )->where('is_active', true)->orderBy('name')->get();

        // Presets plus this company's own roles, keyed by ID throughout — see
        // RoleCatalogue for why name is a label here and not an identity.
        $assignableRoles = $this->assignableRoles();
        $rolePermMap     = $this->rolePermMap();

        // Role label per user row in the list, resolved by the role each user actually
        // holds rather than by name lookup.
        $roleDisplayMap = $assignableRoles->pluck('label', 'id')->all();

        return view('livewire.settings.users', compact(
            'users', 'companies', 'outlets', 'regularOutlets', 'kitchens', 'isSuperAdmin', 'modules',
            'moduleGrid', 'assignableRoles', 'rolePermMap', 'roleDisplayMap', 'lastActive'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Roles & Access']);
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
        $this->can_view_all_outlets = false;
        $this->resetValidation();
    }
}
