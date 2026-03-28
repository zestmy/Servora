<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_invoices', function (Blueprint $table) {
            $table->decimal('credit_applied', 15, 4)->default(0)->after('total_amount');
            $table->decimal('balance_due', 15, 4)->nullable()->after('credit_applied');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_invoices', function (Blueprint $table) {
            $table->dropColumn(['credit_applied', 'balance_due']);
        });
    }
};
