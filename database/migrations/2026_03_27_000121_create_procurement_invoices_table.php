<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_transfer_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('goods_received_note_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 30)->unique();
            $table->enum('type', ['supplier', 'cpu_to_outlet']);
            $table->enum('status', ['draft', 'issued', 'paid', 'cancelled', 'overdue'])->default('draft');
            $table->date('issued_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 15, 4);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('delivery_charges', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4);
            $table->char('currency', 3)->default('MYR');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'type', 'status']);
        });

        Schema::create('procurement_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->decimal('unit_price', 15, 4);
            $table->decimal('total_price', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_invoice_lines');
        Schema::dropIfExists('procurement_invoices');
    }
};
