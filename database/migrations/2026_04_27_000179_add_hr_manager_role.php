<?php

use App\Models\OvertimeClaimApprover;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'hr.view', 'guard_name' => 'web']);

        // Create HR Manager role — cross-outlet, approve OT company-wide
        $role = Role::firstOrCreate(['name' => 'HR Manager', 'guard_name' => 'web']);
        $role->givePermissionTo('hr.view');

        // All HR Managers see across outlets
        User::role('HR Manager')->update(['can_view_all_outlets' => true]);

        // Seed a wildcard approver row (outlet_id = null = any outlet) for existing HR Managers
        User::role('HR Manager')->get()->each(function ($u) {
            OvertimeClaimApprover::firstOrCreate([
                'company_id' => $u->company_id,
                'user_id'    => $u->id,
                'outlet_id'  => null,
                'section_id' => null,
            ]);
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::where('name', 'HR Manager')->first();
        $role?->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
