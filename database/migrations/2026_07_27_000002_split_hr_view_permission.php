<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Split the coarse hr.view permission: attendance (incl. service charge)
     * and overtime claims become separate permissions so pay-sensitive pages
     * can be granted independently of the employees list.
     *
     * Zero access change at deploy: every role and every direct user grant
     * that has hr.view receives hr.attendance + hr.claims too (per team).
     */
    public function up(): void
    {
        $now = now();
        $newIds = [];
        foreach (['hr.attendance', 'hr.claims'] as $name) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            if (! $id) {
                $id = DB::table('permissions')->insertGetId([
                    'name' => $name, 'guard_name' => 'web',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $newIds[] = $id;
        }

        $hrViewId = DB::table('permissions')->where('name', 'hr.view')->where('guard_name', 'web')->value('id');
        if ($hrViewId) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $hrViewId)->pluck('role_id') as $roleId) {
                foreach ($newIds as $pid) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $pid, 'role_id' => $roleId,
                    ]);
                }
            }
            foreach (DB::table('model_has_permissions')->where('permission_id', $hrViewId)->get() as $row) {
                foreach ($newIds as $pid) {
                    DB::table('model_has_permissions')->insertOrIgnore([
                        'permission_id' => $pid,
                        'model_type'    => $row->model_type,
                        'model_id'      => $row->model_id,
                        'team_id'       => $row->team_id,
                    ]);
                }
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', ['hr.attendance', 'hr.claims'])->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
