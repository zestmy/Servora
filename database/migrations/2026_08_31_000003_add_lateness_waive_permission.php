<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * hr.clock.waive_lateness — forgive a late charge somebody has incurred.
 *
 * Its own ability rather than riding on hr.clock, the same shape hr.clock.delete
 * already has, and for the same reason: working the review queue and deciding
 * that a charge will not be collected are different jobs. Anyone with hr.clock
 * can already approve a flagged punch — that says the punch is genuine. This
 * says the money attached to a genuine punch is written off, which is a call
 * about somebody's pay rather than about whether they turned up.
 *
 * It is deliberately NOT implied by hr.compensation either. Seeing what a punch
 * costs is what that gate is for, and the override box beside this one already
 * sits behind it; being trusted to READ a figure is not the same as being
 * trusted to cancel it.
 *
 * GRANTED TO: Operations Manager and Company Admin, as asked for. The two
 * system roles are listed with them so the Roles screen tells the truth — they
 * pass every check through isSystemRole() whether or not the row exists, and a
 * screen showing the box unticked for somebody who can plainly do it is worse
 * than no screen. Nobody else gets it, including HR Manager, who owns
 * attendance and payroll but was not named; it is one tick in Settings › Roles
 * if that turns out to be wrong.
 */
return new class extends Migration
{
    private const PERMISSION = 'hr.clock.waive_lateness';

    private const ROLES = ['Super Admin', 'System Admin', 'Company Admin', 'Operations Manager'];

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
