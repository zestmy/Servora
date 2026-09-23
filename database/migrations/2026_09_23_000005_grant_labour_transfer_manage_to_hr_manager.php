<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * hr.compensation.transfers.manage for HR Manager too.
 *
 * The HR Manager already prepares and confirms labour cost transfers (the
 * role holds hr.compensation), so correcting or removing one it confirmed is
 * the same person's job — asked for by Affandy after the ability shipped to
 * Company Admin only.
 */
return new class extends Migration
{
    private const PERMISSION = 'hr.compensation.transfers.manage';

    private const ROLE = 'HR Manager';

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

        foreach (DB::table('roles')->where('name', self::ROLE)->where('guard_name', 'web')->pluck('id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permId, 'role_id' => $roleId,
            ]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permId = DB::table('permissions')
            ->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');
        $roleIds = DB::table('roles')->where('name', self::ROLE)->where('guard_name', 'web')->pluck('id');

        if ($permId && $roleIds->isNotEmpty()) {
            DB::table('role_has_permissions')
                ->where('permission_id', $permId)
                ->whereIn('role_id', $roleIds)
                ->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
