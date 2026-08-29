<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * hr.attendance.service_charge.delete — remove a saved pool.
 *
 * Its own ability rather than riding on hr.attendance.service_charge, the
 * same shape hr.clock.delete and hr.claims.delete already have: setting a
 * pool and destroying one are different acts. A saved pool is the working
 * behind money that has already been handed out, and it is the ONLY copy —
 * payslips keep their own figures, but nothing else records how a point came
 * to be worth what it was.
 *
 * GRANTED NARROWLY, and narrower than the ability it sits beside. The parent
 * went to Operations Manager because that is the person who runs the roster
 * and splits the pool; this one does not, because running a pool every month
 * and deleting one that has been paid out are not the same job. Four roles,
 * all of them administrative. It is one tick in Settings › Roles if an
 * Operations Manager should have it too.
 */
return new class extends Migration
{
    private const PERMISSION = 'hr.attendance.service_charge.delete';

    private const ROLES = ['Super Admin', 'System Admin', 'Company Admin', 'HR Manager'];

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
