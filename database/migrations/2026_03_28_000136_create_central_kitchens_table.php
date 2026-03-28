<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_kitchens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->text('address')->nullable();
            $table->string('contact_person', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('kitchen_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_id')->constrained('central_kitchens')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['manager', 'chef', 'staff'])->default('staff');
            $table->timestamps();
            $table->unique(['kitchen_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_users');
        Schema::dropIfExists('central_kitchens');
    }
};
