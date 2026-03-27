<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('purchase_request_id')->nullable()->after('department_id')
                ->constrained('purchase_requests')->nullOnDelete();
            $table->foreignId('cpu_id')->nullable()->after('purchase_request_id')
                ->constrained('central_purchasing_units')->nullOnDelete();
            $table->enum('source', ['direct', 'cpu_consolidated', 'cpu_passthrough'])->default('direct')
                ->after('cpu_id');
            $table->decimal('delivery_charges', 15, 4)->default(0)->after('tax_amount');
            $table->foreignId('delivery_outlet_id')->nullable()->after('delivery_charges')
                ->constrained('outlets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['purchase_request_id']);
            $table->dropForeign(['cpu_id']);
            $table->dropForeign(['delivery_outlet_id']);
            $table->dropColumn(['purchase_request_id', 'cpu_id', 'source', 'delivery_charges', 'delivery_outlet_id']);
        });
    }
};
