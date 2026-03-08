<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Operations Manager — all operational modules, no business settings
        $opsManager = Role::firstOrCreate(['name' => 'Operations Manager', 'guard_name' => 'web']);
        $opsManager->syncPermissions([
            'ingredients.view',
            'recipes.view',
            'sales.view',
            'inventory.view',
            'purchasing.view',
            'reports.view',
        ]);

        // Remove ingredients.view and recipes.view from Business Manager
        $businessManager = Role::where('name', 'Business Manager')->first();
        if ($businessManager) {
            $businessManager->syncPermissions([
                'sales.view',
                'inventory.view',
                'purchasing.view',
                'reports.view',
                'settings.view',
                'users.manage',
            ]);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Restore Business Manager full permissions
        $businessManager = Role::where('name', 'Business Manager')->first();
        if ($businessManager) {
            $allPerms = Permission::where('guard_name', 'web')->pluck('name')->toArray();
            $businessManager->syncPermissions($allPerms);
        }

        // Remove Operations Manager role
        Role::where('name', 'Operations Manager')->first()?->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
