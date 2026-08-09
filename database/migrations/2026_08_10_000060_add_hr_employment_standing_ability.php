<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * `hr.employment` — employment standing, split out of the employee record.
 *
 * Someone's standing (probation, confirmation, resignation date, active or not,
 * outsourcing) is a different order of information from where they work and what they are
 * called. It now sits behind its own ability and is protected the same way pay is: the
 * fields are hidden, and a value submitted without the ability is ignored rather than
 * trusted — so a forged Livewire payload cannot quietly mark somebody resigned.
 *
 * Scoped to the standing fields rather than the whole Employment tab deliberately. Outlet
 * is required to save an employee and lives in that tab, so hiding all of it would hand
 * anyone who can edit staff a form that fails validation on a field they cannot see.
 *
 * BACKFILL — preserve access exactly: everyone holding `hr.view` receives it, because
 * that is precisely who can edit these fields today.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore([
            'name' => 'hr.employment', 'guard_name' => 'web',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $targetId = DB::table('permissions')
            ->where('name', 'hr.employment')->where('guard_name', 'web')->value('id');
        $sourceId = DB::table('permissions')
            ->where('name', 'hr.view')->where('guard_name', 'web')->value('id');

        if (! $targetId || ! $sourceId) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')->where('permission_id', $sourceId)->pluck('role_id');
        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'role_id' => $roleId, 'permission_id' => $targetId,
            ]);
        }

        $direct = DB::table('model_has_permissions')
            ->where('permission_id', $sourceId)->where('model_type', User::class)
            ->get(['model_id', 'team_id']);

        $rows = [];
        foreach ($direct as $row) {
            $rows[] = [
                'permission_id' => $targetId,
                'model_type'    => User::class,
                'model_id'      => $row->model_id,
                'team_id'       => $row->team_id,
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('model_has_permissions')->insertOrIgnore($chunk);
        }

        logger()->info('[rbac] hr.employment mirrored from hr.view for '
            . $roleIds->count() . ' roles and ' . $direct->count() . ' user grants');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $id = DB::table('permissions')
            ->where('name', 'hr.employment')->where('guard_name', 'web')->value('id');

        if (! $id) {
            return;
        }

        DB::table('role_has_permissions')->where('permission_id', $id)->delete();
        DB::table('model_has_permissions')->where('permission_id', $id)->delete();
        DB::table('permission_denials')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
