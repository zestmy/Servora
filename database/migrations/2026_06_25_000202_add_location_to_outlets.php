<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            // Structured location, used to generate location-specific public
            // holidays (incl. state-level) as calendar/analytics factors.
            $table->string('country', 100)->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn(['country', 'state']);
        });
    }
};
