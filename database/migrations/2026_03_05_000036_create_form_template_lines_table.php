<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_template_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_template_id')->constrained()->cascadeOnDelete();
            $table->enum('item_type', ['ingredient', 'recipe'])->default('ingredient');
            $table->foreignId('ingredient_id')->nullable()->constrained('ingredients')->nullOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->decimal('default_quantity', 12, 4)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_template_lines');
    }
};
