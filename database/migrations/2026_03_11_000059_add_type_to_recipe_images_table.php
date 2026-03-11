<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_images', function (Blueprint $table) {
            $table->string('type', 20)->default('dine_in')->after('recipe_id');
        });
    }

    public function down(): void
    {
        Schema::table('recipe_images', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
