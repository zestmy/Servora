<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wastage_record_lines', function (Blueprint $table) {
            // Make ingredient_id nullable so recipe-only lines are valid
            $table->foreignId('ingredient_id')->nullable()->change();
            // Link to a recipe (for finished/semi-finished product wastage)
            $table->foreignId('recipe_id')->nullable()->after('ingredient_id')
                  ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wastage_record_lines', function (Blueprint $table) {
            $table->dropForeign(['recipe_id']);
            $table->dropColumn('recipe_id');
            $table->foreignId('ingredient_id')->nullable(false)->change();
        });
    }
};
