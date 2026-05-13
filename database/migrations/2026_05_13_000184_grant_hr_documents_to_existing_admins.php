<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Get permissions
        $viewPerm = Permission::where('name', 'hr.documents.view')->first();
        $managePerm = Permission::where('name', 'hr.documents.manage')->first();

        if (!$viewPerm || !$managePerm) return;

        // Grant to all users who have hr.view permission (they likely should see documents too)
        User::permission('hr.view')->get()->each(function ($user) use ($viewPerm, $managePerm) {
            if (!$user->hasPermissionTo('hr.documents.view')) {
                $user->givePermissionTo('hr.documents.view');
            }
            // Company Admins and those with users.manage get manage permission
            if ($user->hasRole('Company Admin') || $user->can_manage_users) {
                if (!$user->hasPermissionTo('hr.documents.manage')) {
                    $user->givePermissionTo('hr.documents.manage');
                }
            }
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No rollback needed
    }
};
