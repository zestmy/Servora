<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $hrView = Permission::where('name', 'hr.view')->where('guard_name', 'web')->first();
        if (! $hrView) {
            $hrView = Permission::create(['name' => 'hr.view', 'guard_name' => 'web']);
        }

        // Grant at the role level for future assignments
        $companyAdmin = Role::where('name', 'Company Admin')->first();
        if ($companyAdmin && ! $companyAdmin->hasPermissionTo('hr.view')) {
            $companyAdmin->givePermissionTo('hr.view');
        }

        // Backfill every existing Company Admin user with the direct permission
        // (permissions are stored directly on users since migration 145).
        if ($companyAdmin) {
            $userIds = DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('role_id', $companyAdmin->id)
                ->pluck('model_id');

            foreach ($userIds as $userId) {
                DB::table('model_has_permissions')->updateOrInsert(
                    ['permission_id' => $hrView->id, 'model_type' => 'App\\Models\\User', 'model_id' => $userId],
                    []
                );
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $hrView = Permission::where('name', 'hr.view')->where('guard_name', 'web')->first();
        if (! $hrView) return;

        $companyAdmin = Role::where('name', 'Company Admin')->first();
        $companyAdmin?->revokePermissionTo('hr.view');

        if ($companyAdmin) {
            $userIds = DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('role_id', $companyAdmin->id)
                ->pluck('model_id');

            DB::table('model_has_permissions')
                ->where('permission_id', $hrView->id)
                ->where('model_type', 'App\\Models\\User')
                ->whereIn('model_id', $userIds)
                ->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
