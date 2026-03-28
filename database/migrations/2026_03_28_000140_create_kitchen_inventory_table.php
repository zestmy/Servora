<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_id')->constrained('central_kitchens')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 15, 4)->default(0);
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['kitchen_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_inventory');
    }
};
