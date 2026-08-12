<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Take `hr.employees.documents.delete` back off HR Manager and Business
 * Manager.
 *
 * The ability was introduced with a backfill that preserved access exactly —
 * everyone who could already delete a scan kept doing so — precisely so that
 * WHO KEEPS IT became a decision somebody makes rather than a side effect of a
 * deploy. This is that decision: deleting an employee's paperwork is
 * irreversible and now sits with the admin roles.
 *
 * Neither role loses anything else. They keep `hr.employees.manage`, so they
 * still create and edit staff, and they keep uploading, viewing and
 * downloading documents — only the half that cannot be taken back has moved.
 *
 * MATCHED ON ROLE NAME, NOT ID. Role ids differ between this developer's
 * database and production — they are not seeded in the same order — so an id
 * hard-coded here would revoke from whichever role happened to hold that
 * number on the other machine. A role absent from an environment is simply
 * skipped.
 *
 * Direct user grants are deliberately untouched. There are none today on
 * either environment, and a direct grant is a named exception somebody made on
 * purpose; sweeping those up in a migration aimed at two roles would remove an
 * individual's access without anybody having asked for it.
 */
return new class extends Migration
{
    private const ABILITY = 'hr.employees.documents.delete';

    private const ROLES = ['HR Manager', 'Business Manager'];

    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', self::ABILITY)->where('guard_name', 'web')->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = DB::table('roles')->whereIn('name', self::ROLES)->pluck('id');

        if ($roleIds->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $roleIds)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reversible, because a permission change made in error should be undone
     * by rolling back rather than by hand-editing a pivot table on a live box.
     */
    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', self::ABILITY)->where('guard_name', 'web')->value('id');

        if (! $permissionId) {
            return;
        }

        foreach (DB::table('roles')->whereIn('name', self::ROLES)->pluck('id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'role_id' => $roleId, 'permission_id' => $permissionId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
