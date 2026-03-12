<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 4)->default(0)->after('total_amount');
            $table->decimal('tax_percent', 5, 2)->default(0)->after('subtotal');
            $table->decimal('tax_amount', 15, 4)->default(0)->after('tax_percent');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'tax_percent', 'tax_amount']);
        });
    }
};
