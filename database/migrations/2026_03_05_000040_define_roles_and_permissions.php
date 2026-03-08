<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'ingredients.view',
            'recipes.view',
            'sales.view',
            'inventory.view',
            'purchasing.view',
            'reports.view',
            'settings.view',
            'users.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // System Admin — app settings + user creation only
        $systemAdmin = Role::firstOrCreate(['name' => 'System Admin', 'guard_name' => 'web']);
        $systemAdmin->syncPermissions(['settings.view', 'users.manage']);

        // Business Manager — full access + user management (company-scoped)
        $businessManager = Role::firstOrCreate(['name' => 'Business Manager', 'guard_name' => 'web']);
        $businessManager->syncPermissions($permissions); // all

        // Manager — all operational modules
        $manager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $manager->syncPermissions([
            'ingredients.view', 'recipes.view', 'sales.view',
            'inventory.view', 'purchasing.view', 'reports.view',
        ]);

        // Chef — kitchen-facing modules
        $chef = Role::firstOrCreate(['name' => 'Chef', 'guard_name' => 'web']);
        $chef->syncPermissions([
            'ingredients.view', 'recipes.view', 'inventory.view', 'purchasing.view',
        ]);

        // Purchasing
        $purchasing = Role::firstOrCreate(['name' => 'Purchasing', 'guard_name' => 'web']);
        $purchasing->syncPermissions(['purchasing.view']);

        // Finance
        $finance = Role::firstOrCreate(['name' => 'Finance', 'guard_name' => 'web']);
        $finance->syncPermissions([
            'sales.view', 'inventory.view', 'purchasing.view', 'reports.view',
        ]);

        // Super Admin — gets all via Gate::before, but also sync explicitly
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($permissions);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['System Admin','Business Manager','Manager','Chef','Purchasing','Finance'] as $role) {
            Role::where('name', $role)->first()?->delete();
        }

        foreach (['ingredients.view','recipes.view','sales.view','inventory.view','purchasing.view','reports.view','settings.view','users.manage'] as $perm) {
            Permission::where('name', $perm)->first()?->delete();
        }
    }
};
