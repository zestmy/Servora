<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * `hr.employees.documents.delete` — removing a scan from an employee record.
 *
 * Deleting a document is the only irreversible action on the Documents tab.
 * The model's `deleted` hook removes the file from disk, deliberately — an IC
 * scan left orphaned on storage would make the delete a lie — so there is no
 * undo and no recycle bin. Until now the act was gated by nothing beyond
 * `hr.employees.manage`, which is "can edit staff": the same clerk who files
 * paperwork all day could destroy it, with a confirm dialog as the only guard.
 *
 * Deliberately NOT folded into `hr.employees.delete`. That one removes the
 * PERSON. Whoever can delete a whole employee can obviously dispose of their
 * documents along with them, but the reverse does not follow — clearing a
 * superseded typhoid card is routine, and deleting a member of staff is not.
 *
 * BACKFILL — preserve access exactly, the same rule `hr.employment` followed.
 * Every role holding `hr.employees.manage` receives this, because that is
 * precisely who can delete a document today. Nobody loses a capability the
 * moment this deploys; revoking it is one toggle per role on the roles screen,
 * which is the decision this migration exists to make possible. Introducing a
 * permission that silently strips access is the worse failure: the button just
 * stops being there and nothing says why.
 */
return new class extends Migration
{
    private const ABILITY = 'hr.employees.documents.delete';
    private const SOURCE  = 'hr.employees.manage';

    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore([
            'name' => self::ABILITY, 'guard_name' => 'web',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $targetId = DB::table('permissions')
            ->where('name', self::ABILITY)->where('guard_name', 'web')->value('id');
        $sourceId = DB::table('permissions')
            ->where('name', self::SOURCE)->where('guard_name', 'web')->value('id');

        if (! $targetId || ! $sourceId) {
            return;
        }

        // Roles first — this is how access is granted in almost every case.
        $roleIds = DB::table('role_has_permissions')->where('permission_id', $sourceId)->pluck('role_id');
        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'role_id' => $roleId, 'permission_id' => $targetId,
            ]);
        }

        /*
         * And direct grants, which exist on this system and would otherwise be
         * quietly downgraded. Spatie's TEAMS mode keys these on the company, so
         * the team column is carried across rather than defaulted — dropping it
         * would hand somebody the ability under the wrong tenant.
         */
        $teamKey = config('permission.column_names.team_foreign_key', 'company_id');

        $direct = DB::table('model_has_permissions')->where('permission_id', $sourceId)->get();
        foreach ($direct as $row) {
            DB::table('model_has_permissions')->insertOrIgnore(array_filter([
                'permission_id' => $targetId,
                'model_type'    => $row->model_type,
                'model_id'      => $row->model_id,
                $teamKey        => $row->{$teamKey} ?? null,
            ], fn ($v) => $v !== null));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $id = DB::table('permissions')
            ->where('name', self::ABILITY)->where('guard_name', 'web')->value('id');

        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
