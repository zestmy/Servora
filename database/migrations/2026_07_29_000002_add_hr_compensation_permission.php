<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * hr.compensation — the pay-sensitive slice of HR: employee salary, service
     * point entitlement, and the service charge distribution workings.
     *
     * Unlike the hr.view split, this one deliberately does NOT inherit from an
     * existing permission. Salary defaults to nobody: only the roles that are
     * already trusted with company-wide money and people data get it, and
     * anyone else must be granted it explicitly in Settings > Users.
     */
    public function up(): void
    {
        $now = now();

        $permId = DB::table('permissions')
            ->where('name', 'hr.compensation')->where('guard_name', 'web')->value('id');

        if (! $permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => 'hr.compensation', 'guard_name' => 'web',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', ['Super Admin', 'System Admin', 'Company Admin', 'Business Manager', 'HR Manager'])
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
            ->where('name', 'hr.compensation')->where('guard_name', 'web')->value('id');

        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
