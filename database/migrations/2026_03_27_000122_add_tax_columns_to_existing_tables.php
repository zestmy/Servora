<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('tax_amount')
                ->constrained('tax_rates')->nullOnDelete();
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 4)->default(0)->after('notes');
            $table->foreignId('tax_rate_id')->nullable()->after('subtotal')
                ->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 15, 4)->default(0)->after('tax_rate_id');
            $table->decimal('delivery_charges', 15, 4)->default(0)->after('tax_amount');
            $table->decimal('total_amount', 15, 4)->default(0)->after('delivery_charges');
        });

        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 4)->default(0)->after('total_amount');
            $table->foreignId('tax_rate_id')->nullable()->after('subtotal')
                ->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 15, 4)->default(0)->after('tax_rate_id');
            $table->decimal('delivery_charges', 15, 4)->default(0)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->dropColumn('tax_rate_id');
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->dropColumn(['subtotal', 'tax_rate_id', 'tax_amount', 'delivery_charges', 'total_amount']);
        });

        Schema::table('goods_received_notes', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->dropColumn(['subtotal', 'tax_rate_id', 'tax_amount', 'delivery_charges']);
        });
    }
};
