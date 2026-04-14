<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->boolean('exclude_from_lms')->default(false)->after('is_active');
            $table->integer('menu_sort_order')->default(0)->after('exclude_from_lms');
            $table->index(['company_id', 'menu_sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'menu_sort_order']);
            $table->dropColumn(['exclude_from_lms', 'menu_sort_order']);
        });
    }
};
