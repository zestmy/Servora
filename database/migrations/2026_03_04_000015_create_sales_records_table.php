<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number')->nullable();
            $table->date('sale_date');
            $table->decimal('total_revenue', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sales_record_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_revenue', 15, 4);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_record_lines');
        Schema::dropIfExists('sales_records');
    }
};
