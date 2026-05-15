<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roster_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->decimal('normal_hours', 8, 2)->default(8.00);
            $table->integer('rest_duration')->default(60); // in minutes
            $table->string('week_start_day', 20)->default('monday');
            $table->timestamps();

            $table->unique('outlet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_settings');
    }
};
