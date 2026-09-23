<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * hr.compensation.transfers.manage — edit or delete a labour cost transfer
 * after it is confirmed.
 *
 * Its own ability rather than riding on hr.compensation, the same shape
 * hr.claims.delete has: preparing a transfer and rewriting one that has
 * already moved cost between outlets in the labour reports are different
 * acts. Granted to Company Admin (and the system roles); it is one tick in
 * Settings › Roles for anyone else who should have it.
 */
return new class extends Migration
{
    private const PERMISSION = 'hr.compensation.transfers.manage';

    private const ROLES = ['Super Admin', 'System Admin', 'Company Admin'];

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
