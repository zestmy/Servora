<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * `hr.employees.documents.delete` — Company Admin, and nobody else.
 *
 * Deleting a scan takes the file off disk with no undo, and the roles holding
 * it had inherited it from `hr.employees.manage` rather than from anyone
 * deciding they should have it. On production that meant Chef, Purchasing,
 * Branch Manager and Operations Manager could destroy an employee's IC scan.
 * This narrows it to the one role that is meant to be able to.
 *
 * Written as an INVARIANT rather than a list of roles to remove: whoever is
 * holding it, only Company Admin is holding it afterwards. A revoke-these-five
 * migration would have been correct on the two databases I could see and wrong
 * on any other, because the roles differ per environment — this cannot be.
 *
 * SUPER ADMIN IS UNAFFECTED IN PRACTICE, and pretending otherwise would be the
 * dangerous kind of tidy. `Gate::before` in AppServiceProvider returns true for
 * Super Admin on every ability, so the row is removed for consistency but that
 * account can still delete documents. That is what a platform account IS; the
 * way to stop it is to not hold the role, not to edit a pivot table.
 *
 * Direct user grants are cleared too, because "only Company Admin" cannot be
 * true while an individual holds it directly. There are none on either
 * environment today, so this removes nothing from anybody — it is here so the
 * invariant is enforced rather than merely intended.
 *
 * Nobody loses anything else. Every affected role keeps `hr.employees.manage`,
 * so they still create and edit staff, and still upload, view and download
 * documents. Only the irreversible half moved.
 */
return new class extends Migration
{
    private const ABILITY = 'hr.employees.documents.delete';
    private const KEEP    = 'Company Admin';

    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', self::ABILITY)->where('guard_name', 'web')->value('id');

        if (! $permissionId) {
            return;
        }

        /*
         * Absent on some environments — the test database seeds nine roles and
         * Company Admin is not among them — and the invariant still holds
         * there: "only Company Admin" with no such role means nobody, which is
         * the safe direction for an irreversible action. The revoke below is
         * therefore unconditional rather than skipped, and the null keeps the
         * `when()` from excluding a role id that does not exist.
         */
        $keepId = DB::table('roles')->where('name', self::KEEP)->value('id');

        // Granted rather than assumed: on an environment where Company Admin
        // never held it, the end state must still be "Company Admin can".
        if ($keepId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'role_id' => $keepId, 'permission_id' => $permissionId,
            ]);
        }

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->when($keepId, fn ($q) => $q->where('role_id', '!=', $keepId))
            ->delete();

        DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverses to the state the previous migration left: every role that can
     * edit staff, less HR Manager and Business Manager.
     *
     * Reconstructed rather than guessed — the backfill granted this to exactly
     * the holders of `hr.employees.manage`, and 000050 removed those two — so
     * rolling back lands where it started instead of somewhere plausible.
     */
    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', self::ABILITY)->where('guard_name', 'web')->value('id');
        $manageId = DB::table('permissions')
            ->where('name', 'hr.employees.manage')->where('guard_name', 'web')->value('id');

        if (! $permissionId || ! $manageId) {
            return;
        }

        $revoked = DB::table('roles')
            ->whereIn('name', ['HR Manager', 'Business Manager'])->pluck('id');

        $restore = DB::table('role_has_permissions')
            ->where('permission_id', $manageId)->pluck('role_id')
            ->reject(fn ($id) => $revoked->contains($id));

        foreach ($restore as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'role_id' => $roleId, 'permission_id' => $permissionId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
