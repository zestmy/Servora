<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        Permission::firstOrCreate(['name' => 'hr.documents.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'hr.documents.manage', 'guard_name' => 'web']);

        // Grant hr.documents.view to: Company Admin, Business Manager, HR Manager
        foreach (['Company Admin', 'Business Manager', 'HR Manager'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && !$role->hasPermissionTo('hr.documents.view')) {
                $role->givePermissionTo('hr.documents.view');
            }
        }

        // Grant hr.documents.manage to: Company Admin, HR Manager
        foreach (['Company Admin', 'HR Manager'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && !$role->hasPermissionTo('hr.documents.manage')) {
                $role->givePermissionTo('hr.documents.manage');
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Revoke from roles
        foreach (['Company Admin', 'Business Manager', 'HR Manager'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            $role?->revokePermissionTo('hr.documents.view');
            $role?->revokePermissionTo('hr.documents.manage');
        }

        // Delete permissions
        Permission::where('name', 'hr.documents.view')->delete();
        Permission::where('name', 'hr.documents.manage')->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
