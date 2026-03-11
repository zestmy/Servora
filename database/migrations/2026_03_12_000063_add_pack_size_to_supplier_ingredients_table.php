<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_ingredients', function (Blueprint $table) {
            $table->decimal('pack_size', 15, 4)->default(1)->after('uom_id');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_ingredients', function (Blueprint $table) {
            $table->dropColumn('pack_size');
        });
    }
};
