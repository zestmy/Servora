<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_request_supplier_id')->constrained('quotation_request_suppliers')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('quotation_number', 30)->unique();
            $table->enum('status', ['draft', 'submitted', 'accepted', 'rejected'])->default('draft');
            $table->date('valid_until')->nullable();
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('delivery_charges', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_request_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->decimal('unit_price', 15, 4);
            $table->decimal('total_price', 15, 4);
            $table->enum('price_type', ['listed', 'discounted', 'tender'])->default('listed');
            $table->decimal('discount_percent', 6, 2)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_quotation_lines');
        Schema::dropIfExists('supplier_quotations');
    }
};
