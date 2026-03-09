<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->string('type', 20)->default('monthly'); // monthly, daily
            $table->decimal('target_revenue', 14, 2);
            $table->unsignedInteger('target_pax')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'outlet_id', 'period', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
    }
};
