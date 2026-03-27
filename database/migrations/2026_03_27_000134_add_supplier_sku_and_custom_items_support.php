<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add supplier product reference to PO lines
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->string('supplier_sku', 50)->nullable()->after('ingredient_id');
            $table->string('supplier_product_name', 200)->nullable()->after('supplier_sku');
        });

        // Add custom item support to PR lines (ingredient_id becomes nullable)
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->string('custom_name', 200)->nullable()->after('ingredient_id');
        });

        // Make ingredient_id nullable on PR lines for custom items
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn(['supplier_sku', 'supplier_product_name']);
        });

        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->dropColumn('custom_name');
            $table->unsignedBigInteger('ingredient_id')->nullable(false)->change();
        });
    }
};
