<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * kitchen_inventory and production_logs were the only kitchen tables with
     * no company_id and no CompanyScope, and the kitchen dashboard queries
     * both unfiltered — a second company using the kitchen workspace would
     * have read the first one's stock levels and production history.
     *
     * Nothing has leaked yet (production currently has a single kitchen and
     * zero rows in both tables), so this is a pre-emptive close.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('kitchen_inventory', 'company_id')) {
            Schema::table('kitchen_inventory', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')
                    ->constrained()->cascadeOnDelete();
                $table->index(['company_id', 'kitchen_id']);
            });

            // Owning company comes from the kitchen the stock sits in.
            DB::statement('
                UPDATE kitchen_inventory ki
                JOIN central_kitchens ck ON ck.id = ki.kitchen_id
                SET ki.company_id = ck.company_id
            ');
        }

        if (! Schema::hasColumn('production_logs', 'company_id')) {
            Schema::table('production_logs', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')
                    ->constrained()->cascadeOnDelete();
                $table->index(['company_id', 'produced_at']);
            });

            // Owning company comes from the order the log belongs to.
            DB::statement('
                UPDATE production_logs pl
                JOIN production_orders po ON po.id = pl.production_order_id
                SET pl.company_id = po.company_id
            ');
        }
    }

    public function down(): void
    {
        Schema::table('kitchen_inventory', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'kitchen_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'produced_at']);
            $table->dropColumn('company_id');
        });
    }
};
