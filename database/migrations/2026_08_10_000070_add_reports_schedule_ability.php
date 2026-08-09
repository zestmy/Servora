<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * `reports.schedule` — scheduled report subscriptions, split from reading reports.
 *
 * Reading a report on screen and mailing it out on a schedule are different powers. A
 * subscription sends company figures to whatever address is configured, on repeat, with
 * nobody watching — closer to data egress than to reporting. It was gated on
 * `reports.view`, so anyone who could open the reports hub could also set that up.
 *
 * BACKFILL — preserve access exactly: everyone holding `reports.view` receives it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore([
            'name' => 'reports.schedule', 'guard_name' => 'web',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $targetId = DB::table('permissions')
            ->where('name', 'reports.schedule')->where('guard_name', 'web')->value('id');
        $sourceId = DB::table('permissions')
            ->where('name', 'reports.view')->where('guard_name', 'web')->value('id');

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

        logger()->info('[rbac] reports.schedule mirrored from reports.view for '
            . $roleIds->count() . ' roles and ' . $direct->count() . ' user grants');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $id = DB::table('permissions')
            ->where('name', 'reports.schedule')->where('guard_name', 'web')->value('id');

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
