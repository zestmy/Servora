<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * hr.leave — see and apply for leave and time off.
 * hr.leave.approve — decide on someone else's request, and grant entitlement.
 *
 * Split for the same reason as every other approval here: applying is
 * everybody's, deciding is not.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'hr.leave' => [
            'Super Admin', 'System Admin', 'Company Admin', 'Business Manager',
            'HR Manager', 'Area Manager', 'Outlet Manager',
        ],
        'hr.leave.approve' => [
            'Super Admin', 'System Admin', 'Company Admin', 'Business Manager', 'HR Manager',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $permission => $roles) {
            $permId = DB::table('permissions')
                ->where('name', $permission)->where('guard_name', 'web')->value('id');

            if (! $permId) {
                $permId = DB::table('permissions')->insertGetId([
                    'name' => $permission, 'guard_name' => 'web',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $roleIds = DB::table('roles')
                ->whereIn('name', $roles)->where('guard_name', 'web')->pluck('id');

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permId, 'role_id' => $roleId,
                ]);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (array_keys(self::PERMISSIONS) as $permission) {
            $id = DB::table('permissions')
                ->where('name', $permission)->where('guard_name', 'web')->value('id');

            if ($id) {
                DB::table('role_has_permissions')->where('permission_id', $id)->delete();
                DB::table('model_has_permissions')->where('permission_id', $id)->delete();
                DB::table('permissions')->where('id', $id)->delete();
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
