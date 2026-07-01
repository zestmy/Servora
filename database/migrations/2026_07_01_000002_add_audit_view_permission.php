<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Introduce the `audit.view` permission and grant it to admins + all managers.
 * Permissions are stored directly on users in this app (see migration 145), so
 * we both attach it to the roles (for future users) and backfill every existing
 * user who currently holds one of those roles.
 */
return new class extends Migration
{
    private array $roles = [
        'Super Admin', 'System Admin', 'Business Manager', 'Company Admin',
        'Manager', 'Operations Manager', 'Branch Manager',
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => 'audit.view', 'guard_name' => 'web']);

        foreach ($this->roles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            if (! $role->hasPermissionTo('audit.view')) {
                $role->givePermissionTo('audit.view');
            }

            // Backfill direct user permissions for everyone holding this role.
            $userIds = DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('role_id', $role->id)
                ->pluck('model_id');

            foreach ($userIds as $userId) {
                DB::table('model_has_permissions')->updateOrInsert(
                    ['permission_id' => $perm->id, 'model_type' => 'App\\Models\\User', 'model_id' => $userId],
                    []
                );
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::where('name', 'audit.view')->where('guard_name', 'web')->first();
        if (! $perm) {
            return;
        }

        DB::table('model_has_permissions')->where('permission_id', $perm->id)->delete();
        $perm->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
