<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories for the asset register — Utensils, Appliances, Equipment, Furniture.
 *
 * Its own table rather than sharing `ingredient_categories`: a category list is
 * something a company curates, and mixing "Dairy" into the list you file a
 * combi oven under makes both lists worse. Same shape as the ingredient one so
 * the screens behave identically, including the parent/child nesting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('#14b8a6');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
