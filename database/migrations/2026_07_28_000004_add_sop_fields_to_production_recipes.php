<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_recipes', function (Blueprint $table) {
            $table->string('video_url', 500)->nullable()->after('description');
        });

        Schema::create('production_recipe_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('title')->nullable();
            $table->text('instruction');
            $table->string('image_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_recipe_steps');
        Schema::table('production_recipes', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};
