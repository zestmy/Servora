<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('total_cost')
                ->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 15, 4)->default(0)->after('tax_rate_id');
        });

        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('notes')
                ->constrained('tax_rates')->nullOnDelete();
        });

        Schema::table('delivery_order_lines', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('condition')
                ->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 15, 4)->default(0)->after('tax_rate_id');
        });

        Schema::table('goods_received_note_lines', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('condition')
                ->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 15, 4)->default(0)->after('tax_rate_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
            $table->dropColumn('tax_amount');
        });

        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
        });

        Schema::table('delivery_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
            $table->dropColumn('tax_amount');
        });

        Schema::table('goods_received_note_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
            $table->dropColumn('tax_amount');
        });
    }
};
