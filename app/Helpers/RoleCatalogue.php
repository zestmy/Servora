<?php

namespace App\Helpers;

use App\Livewire\Settings\Users as SettingsUsers;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which roles may be assigned inside a given company, and what each one grants.
 *
 * Two tiers, and the schema already had room for both — `roles.team_id` has existed since
 * Spatie teams mode was switched on, with a unique index on (team_id, name, guard_name):
 *
 *   team_id NULL  a system preset, visible to every company, editable only by a Servora
 *                 system administrator in Admin > Role Templates.
 *   team_id = N   company N's own role. Invisible to every other company, editable by
 *                 that company's admins.
 *
 * Spatie resolves `team_id IS NULL OR team_id = getPermissionsTeamId()` natively
 * (Models\Role::findByParam), so a preset and a company role work identically once
 * assigned.
 *
 * ---------------------------------------------------------------------------
 * EVERYTHING HERE KEYS ON ROLE ID, NEVER ON NAME.
 * ---------------------------------------------------------------------------
 * The unique index is (team_id, name, guard_name), so a company MAY create its own "Chef"
 * beside the global one — and `Role::findByName()` ends in `->first()`, which would then
 * return whichever row the database felt like. Worse, the old `rolePermMap()` grouped by
 * role NAME, so two roles sharing one would have had their permission sets silently
 * merged. Name is a label here; id is the identity.
 */
class RoleCatalogue
{
    /**
     * Assignable roles for a company: the presets, then that company's own.
     *
     * @return Collection<int, object{id:int,name:string,label:string,description:string,team_id:?int,is_preset:bool}>
     */
    public static function forCompany(?int $companyId): Collection
    {
        $presetNames = array_keys(SettingsUsers::ASSIGNABLE_ROLES);

        return DB::table('roles')
            ->where('guard_name', 'web')
            ->where(function ($q) use ($presetNames, $companyId) {
                $q->where(fn ($qq) => $qq->whereNull('team_id')->whereIn('name', $presetNames));
                if ($companyId) {
                    $q->orWhere('team_id', $companyId);
                }
            })
            ->get(['id', 'name', 'display_name', 'description', 'team_id'])
            // Presets first and in their declared order, then the company's own by name.
            ->sortBy(fn ($r) => $r->team_id === null
                ? [0, array_search($r->name, $presetNames, true)]
                : [1, 0])
            ->values()
            ->map(fn ($r) => (object) [
                'id'          => (int) $r->id,
                'name'        => $r->name,
                'label'       => $r->display_name ?: $r->name,
                'description' => $r->description ?: (SettingsUsers::ASSIGNABLE_ROLES[$r->name] ?? ''),
                'team_id'     => $r->team_id === null ? null : (int) $r->team_id,
                'is_preset'   => $r->team_id === null,
            ]);
    }

    /**
     * Permission names each role grants, keyed by role ID.
     *
     * @param  iterable<int>  $roleIds
     * @return array<int, list<string>>
     */
    public static function permissionsByRoleId(iterable $roleIds): array
    {
        $ids = collect($roleIds)->map(fn ($i) => (int) $i)->all();

        if ($ids === []) {
            return [];
        }

        return DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('role_has_permissions.role_id', $ids)
            ->select('role_has_permissions.role_id', 'permissions.name')
            ->get()
            ->groupBy('role_id')
            ->map(fn ($rows) => $rows->pluck('name')->values()->all())
            ->all();
    }

    /** How many distinct users hold each role, optionally scoped to one company. */
    public static function userCounts(?int $companyId = null): array
    {
        return DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->when($companyId, fn ($q) => $q->where('team_id', $companyId))
            ->selectRaw('role_id, COUNT(DISTINCT model_id) c')
            ->groupBy('role_id')
            ->pluck('c', 'role_id')
            ->all();
    }

    /** The role a user holds in one company, or null. Returns the catalogue row. */
    public static function roleFor(int $userId, int $companyId): ?object
    {
        $roleId = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $userId)
            ->where('team_id', $companyId)
            ->value('role_id');

        if (! $roleId) {
            return null;
        }

        return self::forCompany($companyId)->firstWhere('id', (int) $roleId);
    }
}
