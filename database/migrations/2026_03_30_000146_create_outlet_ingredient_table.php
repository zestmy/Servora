<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_ingredient', function (Blueprint $table) {
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->unique(['outlet_id', 'ingredient_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_ingredient');
    }
};
