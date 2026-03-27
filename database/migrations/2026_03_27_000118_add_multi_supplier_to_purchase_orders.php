<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->boolean('is_multi_supplier')->default(false)->after('delivery_outlet_id');
            $table->foreignId('parent_po_id')->nullable()->after('is_multi_supplier')
                ->constrained('purchase_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['parent_po_id']);
            $table->dropColumn(['is_multi_supplier', 'parent_po_id']);
        });
    }
};
