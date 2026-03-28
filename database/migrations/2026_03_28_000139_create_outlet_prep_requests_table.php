<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_prep_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_id')->constrained('central_kitchens')->cascadeOnDelete();
            $table->string('request_number', 30)->unique();
            $table->enum('status', ['draft', 'submitted', 'approved', 'scheduled', 'fulfilled', 'cancelled'])->default('draft');
            $table->date('needed_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status']);
        });

        Schema::create('outlet_prep_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_prep_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('requested_quantity', 15, 4);
            $table->decimal('fulfilled_quantity', 15, 4)->default(0);
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_prep_request_lines');
        Schema::dropIfExists('outlet_prep_requests');
    }
};
