<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Create roster amendments table to track changes to approved rosters
        Schema::create('roster_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('roster_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amended_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->json('changes'); // {field: {from: x, to: y}}
            $table->boolean('notified')->default(false);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['roster_id', 'created_at']);
        });

        // Add roster.amend permission
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::where('name', 'roster.amend')->where('guard_name', 'web')->first();
        if (!$perm) {
            $perm = Permission::create(['name' => 'roster.amend', 'guard_name' => 'web']);
        }

        // Grant to Company Admin and HR Manager
        foreach (['Company Admin', 'HR Manager'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && !$role->hasPermissionTo('roster.amend')) {
                $role->givePermissionTo('roster.amend');
            }
        }

        // Backfill existing Company Admin users
        $companyAdmin = Role::where('name', 'Company Admin')->first();
        if ($companyAdmin) {
            $userIds = DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('role_id', $companyAdmin->id)
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
        Schema::dropIfExists('roster_amendments');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $perm = Permission::where('name', 'roster.amend')->where('guard_name', 'web')->first();
        if ($perm) {
            DB::table('model_has_permissions')->where('permission_id', $perm->id)->delete();
            DB::table('role_has_permissions')->where('permission_id', $perm->id)->delete();
            $perm->delete();
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
