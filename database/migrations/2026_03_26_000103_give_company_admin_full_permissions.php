<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $companyAdmin = Role::where('name', 'Company Admin')->first();
        if ($companyAdmin) {
            $companyAdmin->syncPermissions([
                'ingredients.view', 'recipes.view', 'sales.view',
                'inventory.view', 'purchasing.view', 'reports.view',
                'settings.view', 'users.manage',
            ]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $companyAdmin = Role::where('name', 'Company Admin')->first();
        if ($companyAdmin) {
            $companyAdmin->syncPermissions([]);
        }
    }
};
