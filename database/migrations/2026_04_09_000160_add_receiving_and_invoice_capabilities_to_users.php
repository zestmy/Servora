<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_receive_grn')->default(false)->after('can_view_all_outlets');
            $table->boolean('can_manage_invoices')->default(false)->after('can_receive_grn');
        });

        // Seed defaults: users who can_approve_po also get receive + invoice capabilities
        DB::table('users')
            ->where('can_approve_po', true)
            ->update([
                'can_receive_grn'     => true,
                'can_manage_invoices' => true,
            ]);

        // Also grant to any Spatie "Super Admin" or "System Admin" role holders
        $systemUserIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', ['Super Admin', 'System Admin'])
            ->pluck('model_has_roles.model_id');

        if ($systemUserIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $systemUserIds)
                ->update([
                    'can_receive_grn'     => true,
                    'can_manage_invoices' => true,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['can_receive_grn', 'can_manage_invoices']);
        });
    }
};
