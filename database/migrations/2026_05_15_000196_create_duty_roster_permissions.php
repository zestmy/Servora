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

        // Create roster permissions
        $permissions = [
            'roster.view',
            'roster.create',
            'roster.edit',
            'roster.delete',
            'roster.approve',
            'roster.settings', // stations, approvers, email recipients
        ];

        foreach ($permissions as $permName) {
            $perm = Permission::where('name', $permName)->where('guard_name', 'web')->first();
            if (! $perm) {
                Permission::create(['name' => $permName, 'guard_name' => 'web']);
            }
        }

        // Grant permissions to roles
        $companyAdmin = Role::where('name', 'Company Admin')->first();
        $hrManager = Role::where('name', 'HR Manager')->first();
        $outletManager = Role::where('name', 'Outlet Manager')->first();

        // Company Admin & HR Manager get all permissions
        foreach ([$companyAdmin, $hrManager] as $role) {
            if ($role) {
                foreach ($permissions as $permName) {
                    if (! $role->hasPermissionTo($permName)) {
                        $role->givePermissionTo($permName);
                    }
                }
            }
        }

        // Outlet Manager gets view, create, edit, delete (but not approve or settings)
        if ($outletManager) {
            foreach (['roster.view', 'roster.create', 'roster.edit', 'roster.delete'] as $permName) {
                if (! $outletManager->hasPermissionTo($permName)) {
                    $outletManager->givePermissionTo($permName);
                }
            }
        }

        // Backfill existing Company Admin users
        if ($companyAdmin) {
            $userIds = DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('role_id', $companyAdmin->id)
                ->pluck('model_id');

            foreach ($permissions as $permName) {
                $perm = Permission::where('name', $permName)->where('guard_name', 'web')->first();
                if ($perm) {
                    foreach ($userIds as $userId) {
                        DB::table('model_has_permissions')->updateOrInsert(
                            ['permission_id' => $perm->id, 'model_type' => 'App\\Models\\User', 'model_id' => $userId],
                            []
                        );
                    }
                }
            }
        }

        // Backfill existing HR Manager users
        if ($hrManager) {
            $userIds = DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('role_id', $hrManager->id)
                ->pluck('model_id');

            foreach ($permissions as $permName) {
                $perm = Permission::where('name', $permName)->where('guard_name', 'web')->first();
                if ($perm) {
                    foreach ($userIds as $userId) {
                        DB::table('model_has_permissions')->updateOrInsert(
                            ['permission_id' => $perm->id, 'model_type' => 'App\\Models\\User', 'model_id' => $userId],
                            []
                        );
                    }
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'roster.view',
            'roster.create',
            'roster.edit',
            'roster.delete',
            'roster.approve',
            'roster.settings',
        ];

        foreach ($permissions as $permName) {
            $perm = Permission::where('name', $permName)->where('guard_name', 'web')->first();
            if ($perm) {
                // Remove from model_has_permissions
                DB::table('model_has_permissions')->where('permission_id', $perm->id)->delete();
                // Remove from role_has_permissions
                DB::table('role_has_permissions')->where('permission_id', $perm->id)->delete();
                // Delete permission
                $perm->delete();
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
