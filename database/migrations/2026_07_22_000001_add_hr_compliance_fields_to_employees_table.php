<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('join_date')->nullable()->after('phone');
            $table->boolean('food_handler_certified')->default(false)->after('join_date');
            $table->boolean('typhoid_card')->default(false)->after('food_handler_certified');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['join_date', 'food_handler_certified', 'typhoid_card']);
        });
    }
};
