<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredient_categories', function (Blueprint $table) {
            // food | beverage | merchandise | retail | other
            // Only set on main (root) categories; sub-categories inherit from parent.
            $table->string('type', 30)->nullable()->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('ingredient_categories', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
