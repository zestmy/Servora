<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Link departments to sales categories for P&L costing
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('sales_category_id')->nullable()->after('name')
                  ->constrained('sales_categories')->nullOnDelete();
        });

        // Step 2: Carry department_id through purchase records
        Schema::table('purchase_records', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('supplier_id')
                  ->constrained('departments')->nullOnDelete();
        });

        // Step 3: Add department_id to stock takes, wastage and staff meal records
        Schema::table('stock_takes', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('outlet_id')
                  ->constrained('departments')->nullOnDelete();
        });

        Schema::table('wastage_records', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('outlet_id')
                  ->constrained('departments')->nullOnDelete();
        });

        Schema::table('staff_meal_records', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('outlet_id')
                  ->constrained('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff_meal_records', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        Schema::table('wastage_records', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        Schema::table('stock_takes', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        Schema::table('purchase_records', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['sales_category_id']);
            $table->dropColumn('sales_category_id');
        });
    }
};
