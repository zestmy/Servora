<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->foreignId('secondary_recipe_uom_id')
                  ->nullable()
                  ->after('recipe_uom_id')
                  ->constrained('units_of_measure')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropForeign(['secondary_recipe_uom_id']);
            $table->dropColumn('secondary_recipe_uom_id');
        });
    }
};
