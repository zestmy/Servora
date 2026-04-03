<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_invoices', function (Blueprint $table) {
            $table->string('supplier_invoice_number', 100)->nullable()->after('invoice_number');
            $table->string('original_file_path')->nullable()->after('notes');
            $table->unsignedBigInteger('ai_invoice_scan_id')->nullable()->after('original_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_invoices', function (Blueprint $table) {
            $table->dropColumn(['supplier_invoice_number', 'original_file_path', 'ai_invoice_scan_id']);
        });
    }
};
