<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Two permissions, split along "reads the queue" versus "changes what
     * the queue costs".
     *
     * hr.clock        — see the punch log and approve or reject flagged ones.
     *                   Inherits from hr.attendance: whoever already keeps the
     *                   attendance grid is the person who will work this queue.
     *
     * hr.clock.manage — the penalty rate, the geofence, and enrolling faces.
     *                   Deliberately does NOT inherit. It sets a RM-per-minute
     *                   charge against staff pay and registers biometric
     *                   templates; both belong with the roles already trusted
     *                   with compensation, and anyone else must be granted it
     *                   by name in Settings > Users.
     */
    private const PERMISSIONS = [
        'hr.clock'        => ['inherit_from' => 'hr.attendance', 'roles' => []],
        'hr.clock.manage' => ['inherit_from' => null, 'roles' => [
            'Super Admin', 'System Admin', 'Company Admin', 'Business Manager', 'HR Manager',
        ]],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $name => $spec) {
            $permId = DB::table('permissions')
                ->where('name', $name)->where('guard_name', 'web')->value('id');

            if (! $permId) {
                $permId = DB::table('permissions')->insertGetId([
                    'name' => $name, 'guard_name' => 'web',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            if ($spec['inherit_from']) {
                $this->inherit($permId, $spec['inherit_from']);
            }

            $roleIds = DB::table('roles')
                ->whereIn('name', $spec['roles'])
                ->where('guard_name', 'web')
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permId, 'role_id' => $roleId,
                ]);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Grant the new permission everywhere the source one is already held —
     * both role grants and the direct per-user grants that Settings > Users
     * hands out, so existing attendance keepers do not have to be re-ticked
     * one by one.
     *
     * model_has_permissions carries a team column in Spatie's teams mode; it
     * is copied verbatim so a per-company grant stays scoped to that company.
     */
    private function inherit(int $permId, string $sourceName): void
    {
        $sourceId = DB::table('permissions')
            ->where('name', $sourceName)->where('guard_name', 'web')->value('id');

        if (! $sourceId) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $sourceId)->pluck('role_id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permId, 'role_id' => $roleId,
            ]);
        }

        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
        $hasTeam = DB::getSchemaBuilder()->hasColumn('model_has_permissions', $teamKey);

        $rows = DB::table('model_has_permissions')->where('permission_id', $sourceId)->get();

        foreach ($rows as $row) {
            $insert = [
                'permission_id' => $permId,
                'model_type'    => $row->model_type,
                'model_id'      => $row->model_id,
            ];

            if ($hasTeam) {
                $insert[$teamKey] = $row->{$teamKey};
            }

            DB::table('model_has_permissions')->insertOrIgnore($insert);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
