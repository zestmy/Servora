<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->string('currency', 10)->default('MYR');
            $table->unsignedInteger('max_outlets')->nullable()->default(1);
            $table->unsignedInteger('max_users')->nullable()->default(5);
            $table->unsignedInteger('max_recipes')->nullable();
            $table->unsignedInteger('max_ingredients')->nullable();
            $table->unsignedInteger('max_lms_users')->nullable();
            $table->json('feature_flags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('trial_days')->default(14);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
