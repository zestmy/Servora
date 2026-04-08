<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_price_class_id')->constrained()->cascadeOnDelete();
            $table->decimal('selling_price', 12, 4)->default(0);
            $table->timestamps();

            $table->unique(['recipe_id', 'recipe_price_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_prices');
    }
};
