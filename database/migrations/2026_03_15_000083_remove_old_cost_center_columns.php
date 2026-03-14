<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove old cost center (ingredient_category_id) from sales_categories
        Schema::table('sales_categories', function (Blueprint $table) {
            $table->dropForeign(['ingredient_category_id']);
            $table->dropColumn('ingredient_category_id');
        });

        // Remove old cost center from purchase_orders (replaced by department_id)
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['ingredient_category_id']);
            $table->dropColumn('ingredient_category_id');
        });

        // Remove old cost center from form_templates (replaced by department_id)
        Schema::table('form_templates', function (Blueprint $table) {
            $table->dropForeign(['ingredient_category_id']);
            $table->dropColumn('ingredient_category_id');
        });

        // Remove is_revenue flag from ingredient_categories (no longer needed)
        Schema::table('ingredient_categories', function (Blueprint $table) {
            $table->dropColumn('is_revenue');
        });
    }

    public function down(): void
    {
        Schema::table('ingredient_categories', function (Blueprint $table) {
            $table->boolean('is_revenue')->default(true)->after('is_active');
        });

        Schema::table('form_templates', function (Blueprint $table) {
            $table->foreignId('ingredient_category_id')->nullable()->after('supplier_id')
                  ->constrained('ingredient_categories')->nullOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('ingredient_category_id')->nullable()->after('supplier_id')
                  ->constrained('ingredient_categories')->nullOnDelete();
        });

        Schema::table('sales_categories', function (Blueprint $table) {
            $table->foreignId('ingredient_category_id')->nullable()->after('type')
                  ->constrained('ingredient_categories')->nullOnDelete();
        });
    }
};
