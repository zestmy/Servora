<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->boolean('is_prep')->default(false)->after('is_active');
            $table->foreignId('prep_recipe_id')
                  ->nullable()
                  ->after('is_prep')
                  ->constrained('recipes')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropForeign(['prep_recipe_id']);
            $table->dropColumn(['is_prep', 'prep_recipe_id']);
        });
    }
};
