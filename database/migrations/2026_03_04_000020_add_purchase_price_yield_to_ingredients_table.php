<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            // purchase_price: what we actually pay per base UOM
            $table->decimal('purchase_price', 15, 4)->default(0)->after('current_cost');
            // yield_percent: usable product after prep (100 = no loss, 50 = half lost)
            $table->decimal('yield_percent', 5, 2)->default(100)->after('purchase_price');
        });

        // Seed purchase_price from current_cost for existing rows (assume 100% yield)
        DB::table('ingredients')->update([
            'purchase_price' => DB::raw('current_cost'),
            'yield_percent'  => 100,
        ]);
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['purchase_price', 'yield_percent']);
        });
    }
};
