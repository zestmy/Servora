<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->enum('source', ['supplier', 'kitchen'])->default('supplier')->after('preferred_supplier_id');
            $table->foreignId('kitchen_id')->nullable()->after('source')
                ->constrained('central_kitchens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->dropForeign(['kitchen_id']);
            $table->dropColumn(['source', 'kitchen_id']);
        });
    }
};
