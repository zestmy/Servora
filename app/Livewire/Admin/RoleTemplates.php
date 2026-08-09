<?php

namespace App\Livewire\Admin;

use App\Livewire\Settings\Users as SettingsUsers;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

/**
 * System Admin: edit the access template behind each assignable role — the
 * display name, description and module permission set that Settings > Users
 * shows (locked modules + Role Guide) when an admin picks an Access Level.
 *
 * Role definitions are GLOBAL: a change here applies to every user holding
 * the role in every company, immediately. The internal role `name` is the
 * stable key referenced by code and is deliberately not editable; the
 * display name is what all screens show. System roles are not editable.
 */
class RoleTemplates extends Component
{
    /** roleId => [display_name, description, perms[]] */
    public array $edit = [];

    /**
     * Permissions this editor manages; anything else a role holds is kept as-is.
     *
     * Was `SettingsUsers::MODULES` — the same stale 23-entry const that drove the
     * Users screen, which is why role templates could not grant payroll or leave
     * either. Both screens now read the registry, so they cannot drift apart.
     *
     * @return array<string, string>
     */
    public static function editablePerms(): array
    {
        return SettingsUsers::modules();
    }

    /**
     * The system presets only — `team_id IS NULL`.
     *
     * Without that filter this screen would also list (and let a Servora admin silently
     * rewrite) roles that individual companies created for themselves, since Phase 3b
     * lets a company make a role sharing a preset's name.
     */
    protected function assignableRoleRows()
    {
        return DB::table('roles')
            ->whereNull('team_id')
            ->whereIn('name', array_keys(SettingsUsers::ASSIGNABLE_ROLES))
            ->orderByRaw("FIELD(name, '" . implode("','", array_keys(SettingsUsers::ASSIGNABLE_ROLES)) . "')")
            ->get(['id', 'name', 'display_name', 'description']);
    }

    public function mount(): void
    {
        $permsByRole = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->select('role_has_permissions.role_id', 'permissions.name')
            ->get()->groupBy('role_id')
            ->map(fn ($rows) => $rows->pluck('name')->all());

        foreach ($this->assignableRoleRows() as $role) {
            $current = $permsByRole[$role->id] ?? [];
            $this->edit[$role->id] = [
                'display_name' => $role->display_name ?: $role->name,
                'description'  => $role->description ?? '',
                'perms'        => array_values(array_intersect($current, array_keys(self::editablePerms()))),
            ];
        }
    }

    public function save(int $roleId): void
    {
        $role = DB::table('roles')->whereNull('team_id')->where('id', $roleId)->first(['id', 'name']);
        if (! $role || ! isset(SettingsUsers::ASSIGNABLE_ROLES[$role->name]) || ! isset($this->edit[$roleId])) {
            return; // unknown, company-owned or system role — never editable here
        }

        $this->validate([
            "edit.$roleId.display_name" => 'required|string|max:100',
            "edit.$roleId.description"  => 'nullable|string|max:255',
        ], [], [
            "edit.$roleId.display_name" => 'display name',
            "edit.$roleId.description"  => 'description',
        ]);

        DB::table('roles')->where('id', $roleId)->update([
            'display_name' => trim($this->edit[$roleId]['display_name']),
            'description'  => trim($this->edit[$roleId]['description']) ?: null,
            'updated_at'   => now(),
        ]);

        $abilitiesBefore = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_id', $roleId)->pluck('permissions.name')->sort()->values()->all();

        // Replace only the editable module set; permissions outside this
        // editor (e.g. roster.view) are preserved untouched.
        $selected = array_values(array_intersect(
            (array) ($this->edit[$roleId]['perms'] ?? []),
            array_keys(self::editablePerms())
        ));
        $editableIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::editablePerms()))
            ->where('guard_name', 'web')
            ->pluck('id', 'name');

        DB::table('role_has_permissions')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $editableIds->values())
            ->delete();
        foreach ($selected as $permName) {
            if (isset($editableIds[$permName])) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $editableIds[$permName],
                    'role_id'       => $roleId,
                ]);
            }
        }

        // A preset is shared by every company, so who changed it and when matters more
        // here than anywhere else in the access system.
        if ($roleModel = \Spatie\Permission\Models\Role::find($roleId)) {
            AuditLogService::log(
                $roleModel,
                'role_template_updated',
                ['name' => trim($this->edit[$roleId]['display_name']), 'abilities' => $selected],
                ['abilities' => $abilitiesBefore]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session()->flash('success', 'Role template "' . $this->edit[$roleId]['display_name'] . '" updated — applies to every user holding this role, in all companies.');
    }

    public function render()
    {
        // Users holding each role (any company, distinct accounts)
        $userCounts = DB::table('model_has_roles')
            ->where('model_type', \App\Models\User::class)
            ->selectRaw('role_id, COUNT(DISTINCT model_id) c')
            ->groupBy('role_id')->pluck('c', 'role_id');

        return view('livewire.admin.role-templates', [
            'roles'      => $this->assignableRoleRows(),
            'modules'    => self::editablePerms(),
            'moduleGrid' => \App\Helpers\PermissionRegistry::grid(),
            'userCounts' => $userCounts,
        ])->layout('layouts.app', ['title' => 'Role Templates']);
    }
}
