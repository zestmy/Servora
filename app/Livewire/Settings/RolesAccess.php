<?php

namespace App\Livewire\Settings;

use App\Helpers\PermissionRegistry;
use App\Helpers\RoleCatalogue;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

/**
 * Settings > Roles & Access — the two read-and-explain halves of the access screen.
 *
 * "Roles" replaces the Role Guide modal, which listed a role's modules as a wall of
 * badges with no structure. "Effective access" is new and answers the question the old
 * screen could not: *why* can this person reach payroll — their role, or something
 * someone ticked for them alone? Before this, that took a query against
 * model_has_permissions with the right team_id.
 *
 * The Users tab lives in Settings\Users, which already owns creating and editing people.
 * Splitting them keeps that component from growing past a thousand lines; the shared tab
 * bar partial makes all three read as one screen.
 *
 * Role editing is deliberately NOT here. Role definitions are still global — one row per
 * role with `team_id` NULL — so a company admin saving "Chef" would change it for every
 * company on the platform. Phase 3 gives each company its own roles; until then this tab
 * shows what a role grants and says who can change it.
 */
class RolesAccess extends Component
{
    #[Url(as: 'tab', except: 'roles')]
    public string $tab = 'roles';

    /** Effective-access tab: whose access is being explained. */
    #[Url(as: 'user', except: '')]
    public string $userId = '';

    /** Roles tab: filter the ability rows. */
    public string $search = '';

    public function selectUser(int $id): void
    {
        $this->userId = (string) $id;
    }

    /** Presets plus this company's own roles, with user counts and ability sets. */
    private function roles(): array
    {
        $companyId = $this->companyId();
        $rows      = RoleCatalogue::forCompany($companyId);
        $perms     = RoleCatalogue::permissionsByRoleId($rows->pluck('id'));

        // A company admin should see their own company's headcount, not the platform's.
        $counts = RoleCatalogue::userCounts(
            Auth::user()->isSystemRole() ? null : $companyId
        );

        $grantable = array_keys(PermissionRegistry::titles());

        return $rows->map(fn ($r) => [
            'id'          => $r->id,
            'name'        => $r->name,
            'label'       => $r->label,
            'description' => $r->description,
            'is_preset'   => $r->is_preset,
            'users'       => (int) ($counts[$r->id] ?? 0),
            // Intersected with the registry: a role may still hold roster.view, which
            // gates nothing and is not shown anywhere else either.
            'abilities'   => array_values(array_intersect($perms[$r->id] ?? [], $grantable)),
        ])->all();
    }

    /** The company whose roles this screen manages. */
    private function companyId(): int
    {
        return (int) Auth::user()->company_id;
    }

    // ---------------------------------------------------------------- role editing

    public ?int   $editingRoleId = null;
    public string $roleLabel     = '';
    public string $roleDesc      = '';
    public array  $roleAbilities = [];

    /** Start a new role for this company, optionally seeded from an existing one. */
    public function newRole(?int $cloneFromId = null): void
    {
        $source = $cloneFromId
            ? RoleCatalogue::forCompany($this->companyId())->firstWhere('id', $cloneFromId)
            : null;

        $this->editingRoleId = null;
        $this->roleLabel     = $source ? $source->label . ' (copy)' : '';
        $this->roleDesc      = $source->description ?? '';
        $this->roleAbilities = $source
            ? array_values(array_intersect(
                RoleCatalogue::permissionsByRoleId([$source->id])[$source->id] ?? [],
                array_keys(PermissionRegistry::titles())
            ))
            : [];

        $this->resetValidation();
        $this->dispatch('open-role-editor');
    }

    /** Edit one of this company's own roles. Presets are never editable here. */
    public function editRole(int $roleId): void
    {
        $role = RoleCatalogue::forCompany($this->companyId())->firstWhere('id', $roleId);

        if (! $role || $role->is_preset) {
            session()->flash('error', 'System roles are shared with every company and cannot be edited here.');
            return;
        }

        $this->editingRoleId = $role->id;
        $this->roleLabel     = $role->label;
        $this->roleDesc      = $role->description;
        $this->roleAbilities = array_values(array_intersect(
            RoleCatalogue::permissionsByRoleId([$role->id])[$role->id] ?? [],
            array_keys(PermissionRegistry::titles())
        ));

        $this->resetValidation();
        $this->dispatch('open-role-editor');
    }

    public function saveRole(): void
    {
        $companyId = $this->companyId();

        $this->validate([
            'roleLabel' => 'required|string|max:100',
            'roleDesc'  => 'nullable|string|max:255',
        ], [], ['roleLabel' => 'role name', 'roleDesc' => 'description']);

        // The internal `name` is the stable key. It is derived from the label once, at
        // creation, and then frozen — renaming a role must not orphan its assignments.
        // Uniqueness is per company, which the (team_id, name, guard_name) index enforces.
        if ($this->editingRoleId) {
            $role = RoleCatalogue::forCompany($companyId)->firstWhere('id', $this->editingRoleId);
            if (! $role || $role->is_preset) {
                session()->flash('error', 'That role cannot be edited here.');
                return;
            }
            $roleId = $role->id;

            DB::table('roles')->where('id', $roleId)->update([
                'display_name' => trim($this->roleLabel),
                'description'  => trim($this->roleDesc) ?: null,
                'updated_at'   => now(),
            ]);
        } else {
            $key  = Str::slug(trim($this->roleLabel), '_') ?: 'role';
            $name = $key;
            $n    = 2;
            while (DB::table('roles')->where('team_id', $companyId)
                    ->where('name', $name)->where('guard_name', 'web')->exists()) {
                $name = $key . '_' . $n++;
            }

            $roleId = DB::table('roles')->insertGetId([
                'team_id'      => $companyId,
                'name'         => $name,
                'display_name' => trim($this->roleLabel),
                'description'  => trim($this->roleDesc) ?: null,
                'guard_name'   => 'web',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        $selected = array_values(array_intersect(
            $this->roleAbilities,
            array_keys(PermissionRegistry::titles())
        ));

        $permIds = DB::table('permissions')
            ->whereIn('name', $selected)->where('guard_name', 'web')->pluck('id');

        DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
        foreach ($permIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id'       => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->editingRoleId = null;
        $this->dispatch('close-role-editor');
        session()->flash('success', 'Role "' . trim($this->roleLabel) . '" saved — it applies to everyone holding it, immediately.');
    }

    /**
     * Delete one of this company's roles. Refused while anyone still holds it: dropping
     * the assignment silently would strip their access with no trace of why.
     */
    public function deleteRole(int $roleId): void
    {
        $companyId = $this->companyId();
        $role      = RoleCatalogue::forCompany($companyId)->firstWhere('id', $roleId);

        if (! $role || $role->is_preset) {
            session()->flash('error', 'System roles are shared with every company and cannot be deleted here.');
            return;
        }

        $holders = DB::table('model_has_roles')
            ->where('role_id', $roleId)->where('team_id', $companyId)->count();

        if ($holders > 0) {
            session()->flash('error', 'This role is still assigned to ' . $holders . ' '
                . Str::plural('person', $holders) . '. Move them to another role first.');
            return;
        }

        DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
        DB::table('roles')->where('id', $roleId)->where('team_id', $companyId)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        session()->flash('success', 'Role deleted.');
    }

    /**
     * Every ability the chosen user holds in the active company, and where it came from.
     *
     * Provenance is the whole point of the tab, so it is resolved from the two grant
     * tables directly rather than from can(): `role` when the user's role carries it,
     * `direct` when someone ticked it for this person alone. An ability can be both, and
     * role wins in the label because that is the one a role change would take away.
     */
    private function effective(?User $subject): ?array
    {
        if (! $subject) {
            return null;
        }

        $companyId = (int) (Auth::user()->isSystemRole()
            ? $subject->company_id
            : Auth::user()->company_id);

        $roleRow = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $subject->id)
            ->where('model_has_roles.team_id', $companyId)
            ->first(['roles.id', 'roles.name', 'roles.display_name']);

        $rolePerms = $roleRow
            ? DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_id', $roleRow->id)->pluck('permissions.name')->all()
            : [];

        $directPerms = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_type', User::class)
            ->where('model_has_permissions.model_id', $subject->id)
            ->where('model_has_permissions.team_id', $companyId)
            ->pluck('permissions.name')->all();

        $isSystem = $subject->isSystemRole();

        $denied = DB::table('permission_denials')
            ->join('permissions', 'permissions.id', '=', 'permission_denials.permission_id')
            ->where('permission_denials.model_type', User::class)
            ->where('permission_denials.model_id', $subject->id)
            ->where('permission_denials.team_id', $companyId)
            ->pluck('permissions.name')->all();

        $sources = [];
        foreach (array_keys(PermissionRegistry::titles()) as $name) {
            $inRole   = in_array($name, $rolePerms, true);
            $inDirect = in_array($name, $directPerms, true);

            $sources[$name] = match (true) {
                // Denials win over everything except a platform account, exactly as
                // Gate::before resolves them — otherwise this tab would cheerfully
                // report access the user does not actually have.
                in_array($name, $denied, true) && ! $isSystem => 'denied',
                $inRole                                       => 'role',
                $inDirect                                     => 'direct',
                // A system role passes canDo() for everything without holding a row.
                $isSystem                                     => 'system',
                default                                       => null,
            };
        }

        return [
            'user'      => $subject,
            'companyId' => $companyId,
            'roleLabel' => $roleRow ? ($roleRow->display_name ?: $roleRow->name) : null,
            'isSystem'  => $isSystem,
            'sources'   => $sources,
            // 'denied' is a source, not a grant — it must not count toward the total.
            'granted'   => count(array_filter($sources, fn ($s) => $s !== null && $s !== 'denied')),
            'total'     => count($sources),
            'added'     => array_keys(array_filter($sources, fn ($s) => $s === 'direct')),
            'removed'   => array_keys(array_filter($sources, fn ($s) => $s === 'denied')),
            'outlets'   => $subject->canViewAllOutlets()
                ? 'All outlets in this company'
                : ($subject->accessibleOutlets()->pluck('name')->implode(', ') ?: 'No outlets assigned'),
        ];
    }

    /** Users this admin may explain — the same visibility rule as the Users tab. */
    private function selectableUsers()
    {
        $user     = Auth::user();
        $isSystem = $user->isSystemRole();

        return User::query()
            ->when(! $isSystem, function ($q) use ($user) {
                $q->where(fn ($qq) => $qq
                    ->where('company_id', $user->company_id)
                    ->orWhereHas('companies', fn ($c) => $c->where('companies.id', $user->company_id)));
                $q->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', ['Super Admin', 'System Admin']));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'company_id']);
    }

    public function render()
    {
        $subject = null;
        if ($this->userId !== '') {
            $subject = $this->selectableUsers()->firstWhere('id', (int) $this->userId);
            $subject = $subject ? User::find($subject->id) : null;
        }

        return view('livewire.settings.roles-access', [
            'roles'      => $this->roles(),
            'moduleGrid' => PermissionRegistry::grid(),
            'titles'     => PermissionRegistry::titles(),
            'users'      => $this->selectableUsers(),
            'effective'  => $this->effective($subject),
            // Presets stay editable only by a Servora system administrator, because one
            // row is shared by every company. A company's own roles are its own business.
            'canEditPresets' => Auth::user()->isSystemRole(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Roles & Access']);
    }
}
