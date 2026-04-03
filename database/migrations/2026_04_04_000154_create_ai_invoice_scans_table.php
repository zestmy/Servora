<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_invoice_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_file_path');
            $table->string('original_file_name');
            $table->enum('status', ['pending', 'processing', 'extracted', 'matched', 'approved', 'rejected', 'failed'])
                  ->default('pending');
            $table->json('raw_extraction')->nullable();
            $table->json('matched_data')->nullable();
            $table->json('exceptions')->nullable();
            $table->unsignedBigInteger('matched_supplier_id')->nullable();
            $table->unsignedBigInteger('matched_po_id')->nullable();
            $table->unsignedBigInteger('matched_grn_id')->nullable();
            $table->unsignedBigInteger('procurement_invoice_id')->nullable();
            $table->string('ai_model_used')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->foreign('matched_supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('matched_po_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->foreign('matched_grn_id')->references('id')->on('goods_received_notes')->nullOnDelete();
            $table->foreign('procurement_invoice_id')->references('id')->on('procurement_invoices')->nullOnDelete();
        });

        // Add FK on procurement_invoices now that ai_invoice_scans exists
        Schema::table('procurement_invoices', function (Blueprint $table) {
            $table->foreign('ai_invoice_scan_id')->references('id')->on('ai_invoice_scans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_invoices', function (Blueprint $table) {
            $table->dropForeign(['ai_invoice_scan_id']);
        });
        Schema::dropIfExists('ai_invoice_scans');
    }
};
