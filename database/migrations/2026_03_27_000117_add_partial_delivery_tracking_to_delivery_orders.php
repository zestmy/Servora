<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('delivery_sequence')->default(1)->after('status');
            $table->boolean('is_final_delivery')->default(false)->after('delivery_sequence');
        });

        Schema::table('delivery_order_lines', function (Blueprint $table) {
            $table->foreignId('purchase_order_line_id')->nullable()->after('delivery_order_id')
                ->constrained('purchase_order_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_sequence', 'is_final_delivery']);
        });

        Schema::table('delivery_order_lines', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_line_id']);
            $table->dropColumn('purchase_order_line_id');
        });
    }
};
