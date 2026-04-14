<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('ingredients_locked')->default(false)->after('require_pr_approval');
            $table->boolean('recipes_locked')->default(false)->after('ingredients_locked');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['ingredients_locked', 'recipes_locked']);
        });
    }
};
