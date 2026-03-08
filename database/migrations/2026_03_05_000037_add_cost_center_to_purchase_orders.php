<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('ingredient_category_id')
                  ->nullable()
                  ->after('supplier_id')
                  ->constrained('ingredient_categories')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['ingredient_category_id']);
            $table->dropColumn('ingredient_category_id');
        });
    }
};
