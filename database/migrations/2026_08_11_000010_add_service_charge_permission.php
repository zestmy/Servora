<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * hr.attendance.service_charge — set a pool and distribute it.
 *
 * REPORTED AS: an Operations Manager, holding the permission literally titled
 * "HR — Attendance & Service Charge", could not see the Service Charge button.
 * The screen gated it on hr.compensation, so the only way to let somebody
 * split a pool was to show them every basic salary in the company.
 *
 * Granted to the roles that can already do it today — everyone holding
 * hr.compensation loses nothing — PLUS Operations Manager, which is the case
 * that was reported.
 *
 * DELIBERATELY NOT granted to Chef, which also holds hr.attendance. Four chefs
 * in production hold it, and handing them everyone's payout figures is a
 * decision to take on purpose rather than as a side effect of this fix. It is
 * one tick in Settings > Roles if that is wanted.
 */
return new class extends Migration
{
    private const PERMISSION = 'hr.attendance.service_charge';

    private const ROLES = [
        'Super Admin', 'System Admin', 'Company Admin', 'HR Manager', 'Operations Manager',
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

        /*
         * Anyone granted hr.compensation directly rather than through a role
         * keeps what they had. Losing an ability in a migration meant to widen
         * one is the failure mode worth spending a query to avoid.
         */
        $compensationId = DB::table('permissions')
            ->where('name', 'hr.compensation')->where('guard_name', 'web')->value('id');

        if ($compensationId) {
            $direct = DB::table('model_has_permissions')
                ->where('permission_id', $compensationId)
                ->get();

            foreach ($direct as $row) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $permId,
                    'model_type'    => $row->model_type,
                    'model_id'      => $row->model_id,
                    'team_id'       => $row->team_id ?? null,
                ]);
            }
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
