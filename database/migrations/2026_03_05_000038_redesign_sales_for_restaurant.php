<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->unsignedSmallInteger('pax')->default(1)->after('notes');
            $table->string('meal_period', 30)->nullable()->after('pax');
        });

        Schema::table('sales_record_lines', function (Blueprint $table) {
            $table->foreignId('ingredient_category_id')
                  ->nullable()
                  ->after('sales_record_id')
                  ->constrained('ingredient_categories')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_record_lines', function (Blueprint $table) {
            $table->dropForeign(['ingredient_category_id']);
            $table->dropColumn('ingredient_category_id');
        });

        Schema::table('sales_records', function (Blueprint $table) {
            $table->dropColumn(['pax', 'meal_period']);
        });
    }
};
