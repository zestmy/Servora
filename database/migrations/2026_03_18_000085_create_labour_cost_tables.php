<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labour_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->date('month'); // stored as first-of-month
            $table->enum('department_type', ['foh', 'boh']);
            $table->decimal('basic_salary', 14, 2)->default(0);
            $table->decimal('service_point', 14, 2)->default(0);
            $table->decimal('epf', 14, 2)->default(0);
            $table->decimal('eis', 14, 2)->default(0);
            $table->decimal('socso', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'outlet_id', 'month', 'department_type'], 'labour_costs_unique');
        });

        Schema::create('labour_cost_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('labour_cost_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labour_cost_allowances');
        Schema::dropIfExists('labour_costs');
    }
};
