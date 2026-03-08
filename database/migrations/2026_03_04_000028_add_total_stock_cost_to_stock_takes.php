<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_takes', function (Blueprint $table) {
            $table->decimal('total_stock_cost', 15, 4)->default(0)->after('total_variance_cost');
        });
    }

    public function down(): void
    {
        Schema::table('stock_takes', function (Blueprint $table) {
            $table->dropColumn('total_stock_cost');
        });
    }
};
