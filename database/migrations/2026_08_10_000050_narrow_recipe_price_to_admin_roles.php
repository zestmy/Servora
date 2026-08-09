<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * `recipes.price` narrowed to Company Admin and Business Manager.
 *
 * Phase 4d separated setting a selling price from editing the recipe, and backfilled it to
 * everyone who held `recipes.view` — which preserved access exactly but left 18 people able
 * to change menu prices. Setting the price of a menu item drives revenue and every margin
 * report, so it now comes from one of two roles and nowhere else.
 *
 * After this the rule is simply: the ability comes from the role. Direct user grants are
 * cleared, so a Chef or a custom-access account no longer carries it as a personal
 * exception. Anyone who genuinely needs it back can be given it by ticking the ability in
 * Settings > Roles & Access, or by holding one of the two roles.
 *
 * Denials are deliberately NOT cleared. A denial on someone who keeps the ability is still
 * meaningful, and clearing it would silently re-grant — the opposite of the intent here.
 * A denial left on someone who no longer holds it is inert and harmless.
 *
 * Measured on production before applying: 14 lose it, 4 keep it.
 */
return new class extends Migration
{
    private const KEEP_ROLES = ['Company Admin', 'Business Manager'];

    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'recipes.price')->where('guard_name', 'web')->value('id');

        if (! $permissionId) {
            return;
        }

        // The two system presets. Matched on team_id NULL as well as name, so a company
        // that later creates its own "Business Manager" does not silently inherit this.
        $keepRoleIds = DB::table('roles')
            ->whereNull('team_id')
            ->whereIn('name', self::KEEP_ROLES)
            ->where('guard_name', 'web')
            ->pluck('id');

        $removedRoles = DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->whereNotIn('role_id', $keepRoleIds)
            ->delete();

        // Idempotent: the two keepers end up holding it whether they did before or not.
        foreach ($keepRoleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'role_id' => $roleId, 'permission_id' => $permissionId,
            ]);
        }

        $removedUsers = DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->where('model_type', User::class)
            ->delete();

        logger()->info(sprintf(
            '[rbac narrow] recipes.price: removed %d role and %d direct grants, kept %d roles (%s)',
            $removedRoles, $removedUsers, $keepRoleIds->count(), implode(', ', self::KEEP_ROLES)
        ));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Restores the Phase 4d breadth: back to everyone holding `recipes.view`, which is
     * what that backfill produced.
     */
    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'recipes.price')->where('guard_name', 'web')->value('id');
        $viewId = DB::table('permissions')
            ->where('name', 'recipes.view')->where('guard_name', 'web')->value('id');

        if (! $permissionId || ! $viewId) {
            return;
        }

        foreach (DB::table('role_has_permissions')->where('permission_id', $viewId)->pluck('role_id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'role_id' => $roleId, 'permission_id' => $permissionId,
            ]);
        }

        foreach (DB::table('model_has_permissions')->where('permission_id', $viewId)
                    ->where('model_type', User::class)->get(['model_id', 'team_id']) as $row) {
            DB::table('model_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId, 'model_type' => User::class,
                'model_id' => $row->model_id, 'team_id' => $row->team_id,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
