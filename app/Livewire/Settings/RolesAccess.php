<?php

namespace App\Livewire\Settings;

use App\Helpers\PermissionRegistry;
use App\Livewire\Settings\Users as SettingsUsers;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

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

    /** Roles assignable in this install, with display name, description and user count. */
    private function roles(): array
    {
        $names = array_keys(SettingsUsers::ASSIGNABLE_ROLES);

        $rows = DB::table('roles')
            ->whereIn('name', $names)
            // Global presets only. A company row would need the team filter Phase 3 adds;
            // until then there are none, and matching on name alone would merge them.
            ->whereNull('team_id')
            ->orderByRaw("FIELD(name, '" . implode("','", $names) . "')")
            ->get(['id', 'name', 'display_name', 'description']);

        $user      = Auth::user();
        $isSystem  = $user->isSystemRole();

        // Holders of each role. A company admin should see their own company's headcount,
        // not the platform's — model_has_roles.team_id is the company (Spatie teams mode).
        $counts = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->when(! $isSystem, fn ($q) => $q->where('team_id', $user->company_id))
            ->selectRaw('role_id, COUNT(DISTINCT model_id) c')
            ->groupBy('role_id')
            ->pluck('c', 'role_id');

        $perms = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('role_has_permissions.role_id', $rows->pluck('id'))
            ->select('role_has_permissions.role_id', 'permissions.name')
            ->get()->groupBy('role_id')
            ->map(fn ($r) => $r->pluck('name')->all());

        $grantable = array_keys(PermissionRegistry::titles());

        return $rows->map(fn ($r) => [
            'id'          => $r->id,
            'name'        => $r->name,
            'label'       => $r->display_name ?: $r->name,
            'description' => $r->description ?: (SettingsUsers::ASSIGNABLE_ROLES[$r->name] ?? ''),
            'users'       => (int) ($counts[$r->id] ?? 0),
            // Intersected with the registry: a role may still hold roster.view, which
            // gates nothing and is not shown anywhere else either.
            'abilities'   => array_values(array_intersect($perms[$r->id] ?? [], $grantable)),
        ])->all();
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
            'canEditRoles' => Auth::user()->isSystemRole(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Roles & Access']);
    }
}
