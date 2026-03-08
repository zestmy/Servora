<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_record_lines', function (Blueprint $table) {
            $table->foreignId('sales_category_id')
                  ->nullable()
                  ->after('recipe_id')
                  ->constrained('sales_categories')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_record_lines', function (Blueprint $table) {
            $table->dropForeign(['sales_category_id']);
            $table->dropColumn('sales_category_id');
        });
    }
};
