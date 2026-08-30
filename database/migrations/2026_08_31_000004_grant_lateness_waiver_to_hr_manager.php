<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HR Manager joins the two roles that may waive a late charge.
 *
 * The migration that created hr.clock.waive_lateness a few hours ago left HR
 * Manager out and said so in as many words — the ability was asked for by name
 * for Operations Manager and Company Admin, and granting it to a role nobody
 * had named would have been inventing a permission out of an absence. Asked for
 * now, so given now.
 *
 * It is arguably the most natural holder of the three. HR Manager already owns
 * attendance and payroll, which is where a waived charge shows up and who
 * fields the question when somebody asks why a deduction did or did not happen.
 *
 * A SECOND MIGRATION rather than an edit to the first. That one has run in
 * production, so changing its ROLES constant would alter a file the schema
 * table already records as applied — it would do nothing on this box, nothing
 * on any box that has already migrated, and would only take effect on a fresh
 * database. The result is an environment that behaves differently depending on
 * when it was first set up, which is the exact class of drift a migration
 * history exists to prevent.
 *
 * The permission itself is not re-created: if it is somehow missing, the grant
 * is skipped rather than a bare row being minted here. It has one owner, and
 * that is the migration that introduced it.
 */
return new class extends Migration
{
    private const PERMISSION = 'hr.clock.waive_lateness';

    private const ROLE = 'HR Manager';

    public function up(): void
    {
        $permId = DB::table('permissions')
            ->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');

        if (! $permId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->where('name', self::ROLE)
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permId, 'role_id' => $roleId,
            ]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Takes the ability back off HR Manager and leaves the permission itself
     * alone — down() here must undo THIS migration, not the one that created it.
     */
    public function down(): void
    {
        $permId = DB::table('permissions')
            ->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id');

        if (! $permId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->where('name', self::ROLE)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')
            ->where('permission_id', $permId)
            ->whereIn('role_id', $roleIds)
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
