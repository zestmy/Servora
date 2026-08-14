<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The `training.*` abilities behind Learning & Development.
 *
 * BACKFILL, and why it is not one rule for all six.
 *
 * `training.portal` REPLACES a gate that already existed: Settings > Training
 * Portal shipped behind `hr.view`, and the screen has not changed — only which
 * module it is filed under. Anyone who could approve a trainee yesterday must
 * be able to approve one today, so it mirrors `hr.view` exactly. `training.view`
 * rides along with it, because the portal screen is inside the module and a
 * person who can administer it and not see it is stranded.
 *
 * The other four are NEW POWERS, not renamed ones. Authoring material,
 * standing in front of the team hosting a session, making a course mandatory,
 * and reading a named employee's wrong answers did not exist before this
 * migration, so there is no prior access to preserve — and mirroring `hr.view`
 * would hand all four to every Chef and Branch Manager on day one. They go to
 * whoever already administers the company (`users.manage`), who can then hand
 * them out from Settings > Users like anything else.
 */
return new class extends Migration
{
    /** New ability => the ability whose holders inherit it. */
    private const BACKFILL = [
        'training.view'    => 'hr.view',
        'training.portal'  => 'hr.view',
        'training.manage'  => 'users.manage',
        'training.host'    => 'users.manage',
        'training.assign'  => 'users.manage',
        'training.reports' => 'users.manage',
    ];

    public function up(): void
    {
        $now = now();

        foreach (array_keys(self::BACKFILL) as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name, 'guard_name' => 'web',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (self::BACKFILL as $target => $source) {
            $targetId = DB::table('permissions')
                ->where('name', $target)->where('guard_name', 'web')->value('id');
            $sourceId = DB::table('permissions')
                ->where('name', $source)->where('guard_name', 'web')->value('id');

            if (! $targetId || ! $sourceId) {
                continue;
            }

            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $sourceId)->pluck('role_id');

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id' => $roleId, 'permission_id' => $targetId,
                ]);
            }

            $direct = DB::table('model_has_permissions')
                ->where('permission_id', $sourceId)->where('model_type', User::class)
                ->get(['model_id', 'team_id']);

            $rows = [];
            foreach ($direct as $row) {
                $rows[] = [
                    'permission_id' => $targetId,
                    'model_type'    => User::class,
                    'model_id'      => $row->model_id,
                    'team_id'       => $row->team_id,
                ];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('model_has_permissions')->insertOrIgnore($chunk);
            }

            logger()->info("[rbac] {$target} mirrored from {$source} for "
                . $roleIds->count() . ' roles and ' . $direct->count() . ' user grants');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (array_keys(self::BACKFILL) as $name) {
            $id = DB::table('permissions')
                ->where('name', $name)->where('guard_name', 'web')->value('id');

            if (! $id) {
                continue;
            }

            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permission_denials')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
