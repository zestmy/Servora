<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * `purchasing.consolidate` — sweeping outstanding purchase requests into orders.
 *
 * It was gated on `purchasing.orders.create`, so anyone who could raise a single order
 * could also convert every outstanding request across outlets into committed spend in one
 * action. Those are different decisions.
 *
 * NOT backfilled from its old gate. Granted to exactly three roles, as specified:
 * Company Admin, Operations Manager and Purchasing. This is one of the few migrations here
 * that removes access rather than preserving it — anyone else who could consolidate loses
 * it, which is the intent.
 *
 * Granted at ROLE level only, deliberately: per-user pinning is what made role changes
 * meaningless in the first place (see the migration that drops direct grants duplicated by
 * a role). If an individual genuinely needs it, tick it for them in Settings > Roles &
 * Access — a deliberate exception rather than a backfill artefact.
 */
return new class extends Migration
{
    private const GRANT_TO = ['Company Admin', 'Operations Manager', 'Purchasing'];

    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore([
            'name' => 'purchasing.consolidate', 'guard_name' => 'web',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', 'purchasing.consolidate')->where('guard_name', 'web')->value('id');

        if (! $permissionId) {
            return;
        }

        // Presets only — matched on team_id NULL as well as name, so a company that later
        // creates its own "Purchasing" role does not silently inherit this.
        $roleIds = DB::table('roles')
            ->whereNull('team_id')
            ->whereIn('name', self::GRANT_TO)
            ->where('guard_name', 'web')
            ->pluck('id');

        // Declarative: after this the ability sits on exactly those roles and nowhere else,
        // so re-running cannot leave a stale grant behind.
        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->whereNotIn('role_id', $roleIds)
            ->delete();

        DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'role_id' => $roleId, 'permission_id' => $permissionId,
            ]);
        }

        logger()->info('[rbac] purchasing.consolidate granted to ' . $roleIds->count()
            . ' roles (' . implode(', ', self::GRANT_TO) . ')');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $id = DB::table('permissions')
            ->where('name', 'purchasing.consolidate')->where('guard_name', 'web')->value('id');

        if (! $id) {
            return;
        }

        DB::table('role_has_permissions')->where('permission_id', $id)->delete();
        DB::table('model_has_permissions')->where('permission_id', $id)->delete();
        DB::table('permission_denials')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
