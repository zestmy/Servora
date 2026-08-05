<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * hr.compensation.approve — sign off a change to someone's basic salary.
 *
 * Deliberately does NOT inherit from hr.compensation. Seeing pay and deciding
 * pay are different jobs: an HR executive who maintains records needs the
 * former, and giving them the latter by default would make the approval step
 * decorative. Granted to the roles that already carry that authority; anyone
 * else must be given it by name in Settings > Users.
 */
return new class extends Migration
{
    private const PERMISSION = 'hr.compensation.approve';

    private const ROLES = [
        'Super Admin', 'System Admin', 'Company Admin', 'Business Manager', 'HR Manager',
    ];

    public function up(): void
    {
        $now = now();

        $permId = DB::table('permissions')
            ->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');

        if (! $permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => self::PERMISSION, 'guard_name' => 'web',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', self::ROLES)
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permId, 'role_id' => $roleId,
            ]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $id = DB::table('permissions')
            ->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');

        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
