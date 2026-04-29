<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->unsignedInteger('transactions')->nullable()->after('pax');
            $table->decimal('gross_revenue', 15, 4)->nullable()->after('total_revenue');
            $table->decimal('discount_amount', 15, 4)->nullable()->after('gross_revenue');
            $table->decimal('tax_amount', 15, 4)->nullable()->after('discount_amount');
            $table->decimal('service_charges', 15, 4)->nullable()->after('tax_amount');
            $table->decimal('rounding_amount', 15, 4)->nullable()->after('service_charges');
        });
    }

    public function down(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->dropColumn([
                'transactions',
                'gross_revenue',
                'discount_amount',
                'tax_amount',
                'service_charges',
                'rounding_amount',
            ]);
        });
    }
};
