<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Label printing permissions.
 *
 *   labels.print     — print labels and manage print sets. Kitchen-floor
 *                      permission: Chef and Staff need it, since the whole
 *                      point is that the people prepping food label it.
 *   labels.manage    — templates, printers, shelf life matrix, settings.
 *                      Configuration, not daily use.
 *   labels.view_log  — the print audit trail. Outlet Manager gets this
 *                      without labels.manage so they can answer an auditor
 *                      about their own outlet without being able to change
 *                      shelf lives.
 */
return new class extends Migration
{
    private const GRANTS = [
        'labels.print' => [
            'Super Admin', 'System Admin', 'Company Admin', 'Business Manager',
            'Operations Manager', 'Outlet Manager', 'Manager', 'Chef', 'Staff',
        ],
        'labels.manage' => [
            'Super Admin', 'System Admin', 'Company Admin', 'Business Manager',
            'Operations Manager',
        ],
        'labels.view_log' => [
            'Super Admin', 'System Admin', 'Company Admin', 'Business Manager',
            'Operations Manager', 'Outlet Manager', 'Manager',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::GRANTS as $permission => $roles) {
            $permId = DB::table('permissions')
                ->where('name', $permission)->where('guard_name', 'web')->value('id');

            if (! $permId) {
                $permId = DB::table('permissions')->insertGetId([
                    'name' => $permission, 'guard_name' => 'web',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $roleIds = DB::table('roles')
                ->whereIn('name', $roles)
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

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', array_keys(self::GRANTS))
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
