<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('original_quantity', 15, 4)->nullable()->after('quantity');
            $table->foreignId('adjusted_by')->nullable()->after('received_quantity')
                ->constrained('users')->nullOnDelete();
            $table->string('adjustment_reason', 255)->nullable()->after('adjusted_by');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropForeign(['adjusted_by']);
            $table->dropColumn(['original_quantity', 'adjusted_by', 'adjustment_reason']);
        });
    }
};
