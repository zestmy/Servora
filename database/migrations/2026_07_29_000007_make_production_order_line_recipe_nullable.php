<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A production order line now points at EITHER an outlet prep item
     * (recipe_id) or a Central Kitchen production recipe
     * (production_recipe_id). recipe_id was created NOT NULL back when prep
     * items were the only option, which blocks the second case.
     *
     * Raw SQL rather than ->change(): the column carries a foreign key, and
     * MODIFY COLUMN keeps the constraint intact while only relaxing nullability.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE production_order_lines MODIFY recipe_id BIGINT UNSIGNED NULL');

        // The batch log has the same constraint, and needs to record which
        // production recipe a batch came from so the history and yield
        // reports can name it.
        DB::statement('ALTER TABLE production_logs MODIFY recipe_id BIGINT UNSIGNED NULL');

        if (! Schema::hasColumn('production_logs', 'production_recipe_id')) {
            Schema::table('production_logs', function (Blueprint $table) {
                $table->foreignId('production_recipe_id')->nullable()->after('recipe_id')
                    ->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropForeign(['production_recipe_id']);
            $table->dropColumn('production_recipe_id');
        });

        // Rows belonging to a production recipe have no prep-item counterpart,
        // so they must go before the columns can be NOT NULL again.
        DB::table('production_logs')->whereNull('recipe_id')->delete();
        DB::statement('ALTER TABLE production_logs MODIFY recipe_id BIGINT UNSIGNED NOT NULL');

        DB::table('production_order_lines')->whereNull('recipe_id')->delete();
        DB::statement('ALTER TABLE production_order_lines MODIFY recipe_id BIGINT UNSIGNED NOT NULL');
    }
};
