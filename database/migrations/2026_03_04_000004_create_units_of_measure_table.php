<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('abbreviation', 20);
            $table->enum('type', ['weight', 'volume', 'count', 'length'])->default('count');
            $table->boolean('is_base_unit')->default(false);
            $table->decimal('base_unit_factor', 15, 6)->default(1);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units_of_measure');
    }
};
