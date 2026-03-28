<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $allPermissions = Permission::all();

        // Capability mapping by role
        $capabilityMap = [
            'Company Admin'      => ['can_manage_users' => true, 'can_approve_po' => true, 'can_approve_pr' => true, 'can_delete_records' => true, 'can_view_all_outlets' => true],
            'Business Manager'   => ['can_manage_users' => true, 'can_approve_po' => true, 'can_approve_pr' => true, 'can_delete_records' => true, 'can_view_all_outlets' => true],
            'Operations Manager' => ['can_manage_users' => false, 'can_approve_po' => true, 'can_approve_pr' => true, 'can_delete_records' => true, 'can_view_all_outlets' => true],
            'Branch Manager'     => ['can_manage_users' => false, 'can_approve_po' => true, 'can_approve_pr' => true, 'can_delete_records' => false, 'can_view_all_outlets' => false],
            'Outlet Manager'     => ['can_manage_users' => false, 'can_approve_po' => false, 'can_approve_pr' => false, 'can_delete_records' => false, 'can_view_all_outlets' => false],
            'Chef'               => ['can_manage_users' => false, 'can_approve_po' => false, 'can_approve_pr' => false, 'can_delete_records' => false, 'can_view_all_outlets' => false],
            'Purchasing'         => ['can_manage_users' => false, 'can_approve_po' => true, 'can_approve_pr' => false, 'can_delete_records' => false, 'can_view_all_outlets' => true],
            'Finance'            => ['can_manage_users' => false, 'can_approve_po' => false, 'can_approve_pr' => false, 'can_delete_records' => false, 'can_view_all_outlets' => true],
            'Staff'              => ['can_manage_users' => false, 'can_approve_po' => false, 'can_approve_pr' => false, 'can_delete_records' => false, 'can_view_all_outlets' => false],
        ];

        // Get all users with their roles
        $users = DB::table('users')->get();

        foreach ($users as $user) {
            // Get user's roles via Spatie pivot table
            $roleIds = DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $user->id)
                ->pluck('role_id');

            if ($roleIds->isEmpty()) continue;

            $roles = DB::table('roles')->whereIn('id', $roleIds)->pluck('name');
            $primaryRole = $roles->first();

            // Skip system roles — they keep their roles
            if (in_array($primaryRole, ['Super Admin', 'System Admin'])) {
                // Set designation for system roles too
                DB::table('users')->where('id', $user->id)->update([
                    'designation' => $primaryRole,
                    'can_manage_users' => true,
                    'can_approve_po' => true,
                    'can_approve_pr' => true,
                    'can_delete_records' => true,
                    'can_view_all_outlets' => true,
                ]);
                continue;
            }

            // For business roles: copy role's permissions directly to user
            $rolePermissionIds = DB::table('role_has_permissions')
                ->whereIn('role_id', $roleIds)
                ->pluck('permission_id')
                ->unique();

            foreach ($rolePermissionIds as $permId) {
                DB::table('model_has_permissions')->updateOrInsert(
                    ['permission_id' => $permId, 'model_type' => 'App\\Models\\User', 'model_id' => $user->id],
                    []
                );
            }

            // Set designation and capabilities
            $capabilities = $capabilityMap[$primaryRole] ?? $capabilityMap['Staff'];
            DB::table('users')->where('id', $user->id)->update(array_merge(
                ['designation' => $primaryRole],
                $capabilities
            ));
        }
    }

    public function down(): void
    {
        // Reset designations and capabilities
        DB::table('users')->update([
            'designation' => null,
            'can_manage_users' => false,
            'can_approve_po' => false,
            'can_approve_pr' => false,
            'can_delete_records' => false,
            'can_view_all_outlets' => false,
        ]);

        // Remove direct user permissions (keep role-based)
        DB::table('model_has_permissions')->where('model_type', 'App\\Models\\User')->delete();
    }
};
