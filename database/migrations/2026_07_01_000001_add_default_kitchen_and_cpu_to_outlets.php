<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->foreignId('default_kitchen_id')->nullable()->after('is_active')
                ->constrained('central_kitchens')->nullOnDelete();
            $table->foreignId('default_cpu_id')->nullable()->after('default_kitchen_id')
                ->constrained('central_purchasing_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropForeign(['default_kitchen_id']);
            $table->dropForeign(['default_cpu_id']);
            $table->dropColumn(['default_kitchen_id', 'default_cpu_id']);
        });
    }
};
