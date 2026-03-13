<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('direct_supplier_order')->default(false)->after('auto_generate_do');
            $table->text('po_cc_emails')->nullable()->after('direct_supplier_order');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['direct_supplier_order', 'po_cc_emails']);
        });
    }
};
