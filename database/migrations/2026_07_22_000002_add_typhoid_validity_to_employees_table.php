<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('typhoid_valid_from')->nullable()->after('typhoid_card');
            $table->date('typhoid_expired_on')->nullable()->after('typhoid_valid_from');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['typhoid_valid_from', 'typhoid_expired_on']);
        });
    }
};
