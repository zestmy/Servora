<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpu_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cpu_id')->constrained('central_purchasing_units')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['manager', 'staff'])->default('staff');
            $table->timestamps();

            $table->unique(['cpu_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpu_users');
    }
};
