<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'hr.view', 'guard_name' => 'web']);

        // Assign hr.view to roles that should access HR features
        foreach (['Super Admin', 'Business Manager', 'Manager', 'System Admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && !$role->hasPermissionTo('hr.view')) {
                $role->givePermissionTo('hr.view');
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['Super Admin', 'Business Manager', 'Manager', 'System Admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            $role?->revokePermissionTo('hr.view');
        }

        Permission::where('name', 'hr.view')->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
