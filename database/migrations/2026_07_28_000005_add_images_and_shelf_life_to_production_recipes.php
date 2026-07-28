<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_recipes', function (Blueprint $table) {
            // Structured shelf life + storage, matching prep items (the old
            // shelf_life_days / storage_temperature stay for legacy reads).
            $table->decimal('shelf_life_value', 8, 2)->nullable()->after('shelf_life_days');
            $table->string('shelf_life_unit', 20)->nullable()->after('shelf_life_value');
            $table->string('storage_instruction', 20)->nullable()->after('shelf_life_unit');
        });

        // Backfill the structured fields from the legacy day count
        DB::table('production_recipes')
            ->whereNotNull('shelf_life_days')
            ->where('shelf_life_days', '>', 0)
            ->update([
                'shelf_life_value' => DB::raw('shelf_life_days'),
                'shelf_life_unit'  => 'days',
            ]);

        Schema::create('production_recipe_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_recipe_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_recipe_images');
        Schema::table('production_recipes', function (Blueprint $table) {
            $table->dropColumn(['shelf_life_value', 'shelf_life_unit', 'storage_instruction']);
        });
    }
};
