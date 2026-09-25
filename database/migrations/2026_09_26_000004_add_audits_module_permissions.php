<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The six abilities of the Audits module (outlet audits, not the activity trail).
 *
 * BACKFILLED FROM THE NEAREST EXISTING ABILITY, exactly as the Assets module
 * was, so the module is not invisible to every role on deploy day. Each new
 * ability goes to the roles and direct grants that already hold its source:
 *
 *   audits.view            ← reports.view                    (read the scores and findings)
 *   audits.conduct         ← inventory.stock_takes.record    (walk the floor and record)
 *   audits.manage          ← training.manage                 (author the forms)
 *   audits.actions.manage  ← inventory.stock_takes.record    (assign and close out the fixes)
 *   audits.reopen          ← inventory.stock_takes.reopen
 *   audits.delete          ← inventory.stock_takes.delete
 *
 * Whoever already counts the walk-in gets to conduct an audit; whoever writes
 * training content gets to write audit forms. A company that wants its QA
 * department apart from operations unticks the columns on the Roles tab.
 */
return new class extends Migration
{
    /** target => source it is backfilled from */
    private const ABILITIES = [
        'audits.view'           => 'reports.view',
        'audits.conduct'        => 'inventory.stock_takes.record',
        'audits.manage'         => 'training.manage',
        'audits.actions.manage' => 'inventory.stock_takes.record',
        'audits.reopen'         => 'inventory.stock_takes.reopen',
        'audits.delete'         => 'inventory.stock_takes.delete',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ABILITIES as $target => $source) {
            $targetId = DB::table('permissions')
                ->where('name', $target)->where('guard_name', 'web')->value('id');

            if (! $targetId) {
                $targetId = DB::table('permissions')->insertGetId([
                    'name' => $target, 'guard_name' => 'web',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $sourceId = DB::table('permissions')
                ->where('name', $source)->where('guard_name', 'web')->value('id');

            if (! $sourceId) {
                continue;
            }

            foreach (DB::table('role_has_permissions')->where('permission_id', $sourceId)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id' => $roleId, 'permission_id' => $targetId,
                ]);
            }

            $direct = DB::table('model_has_permissions')
                ->where('permission_id', $sourceId)->where('model_type', User::class)
                ->get(['model_id', 'team_id']);

            foreach ($direct as $row) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $targetId, 'model_type' => User::class,
                    'model_id' => $row->model_id, 'team_id' => $row->team_id,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', array_keys(self::ABILITIES))
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permission_denials')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
