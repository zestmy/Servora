<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Fix role permissions ─────────────────────────────────────────────

        // Business Manager — ALL modules + settings
        $bm = Role::where('name', 'Business Manager')->first();
        if ($bm) {
            $bm->syncPermissions([
                'ingredients.view', 'recipes.view', 'sales.view',
                'inventory.view', 'purchasing.view', 'reports.view',
                'settings.view', 'users.manage',
            ]);
        }

        // Manager (Outlet Manager) — operational modules except ingredients, recipes, settings
        $manager = Role::where('name', 'Manager')->first();
        if ($manager) {
            $manager->syncPermissions([
                'sales.view', 'inventory.view', 'purchasing.view', 'reports.view',
            ]);
        }

        // Operations Manager stays as-is (all operational, no settings)

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── PO Approvers — BM assigns who can approve POs per outlet ─────────

        Schema::create('po_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['outlet_id', 'user_id']);
        });

        // ── Add approved_by to purchase_orders ───────────────────────────────

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        // ── Add created_by to delivery_orders ────────────────────────────────

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('received_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
        });

        Schema::dropIfExists('po_approvers');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Restore original Manager permissions
        $manager = Role::where('name', 'Manager')->first();
        if ($manager) {
            $manager->syncPermissions([
                'ingredients.view', 'recipes.view', 'sales.view',
                'inventory.view', 'purchasing.view', 'reports.view',
            ]);
        }

        // Restore BM to what migration 48 set
        $bm = Role::where('name', 'Business Manager')->first();
        if ($bm) {
            $bm->syncPermissions([
                'sales.view', 'inventory.view', 'purchasing.view',
                'reports.view', 'settings.view', 'users.manage',
            ]);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
