<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            // The Sales dashboard filters almost every aggregate by outlet + date range
            $table->index(['outlet_id', 'sale_date'], 'sales_records_outlet_sale_date_idx');
            $table->index('sale_date', 'sales_records_sale_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->dropIndex('sales_records_outlet_sale_date_idx');
            $table->dropIndex('sales_records_sale_date_idx');
        });
    }
};
