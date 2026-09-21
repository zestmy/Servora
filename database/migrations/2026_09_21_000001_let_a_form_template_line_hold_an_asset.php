<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Form templates for asset counts.
 *
 * A template line already holds an ingredient or a recipe; an asset count
 * sheet counts neither, so its lines point at an asset instead. Nullable
 * like the other two — a line carries exactly one of the three, which
 * item_type names.
 *
 * Both type columns are MySQL enums, so they are widened here in raw SQL:
 * 'asset_count' for the template, 'asset' for its lines. SQLite (tests) has
 * no enum type, so it needs only the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_template_lines', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->after('recipe_id')->constrained()->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE form_templates MODIFY form_type ENUM('stock_take','purchase_order','wastage','asset_count') NOT NULL");
            DB::statement("ALTER TABLE form_template_lines MODIFY item_type ENUM('ingredient','recipe','asset') NOT NULL DEFAULT 'ingredient'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('form_template_lines')->where('item_type', 'asset')->delete();
            DB::table('form_templates')->where('form_type', 'asset_count')->delete();
            DB::statement("ALTER TABLE form_template_lines MODIFY item_type ENUM('ingredient','recipe') NOT NULL DEFAULT 'ingredient'");
            DB::statement("ALTER TABLE form_templates MODIFY form_type ENUM('stock_take','purchase_order','wastage') NOT NULL");
        }

        if (Schema::hasColumn('form_template_lines', 'asset_id')) {
            Schema::table('form_template_lines', function (Blueprint $table) {
                $table->dropForeign(['asset_id']);
                $table->dropColumn('asset_id');
            });
        }
    }
};
