<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where an outlet physically is, so a clock-in can be checked against it.
     *
     * decimal(10,7) holds seven decimal places — roughly 11mm at the equator.
     * Far finer than any phone GPS fix, but the extra digits cost nothing and
     * a float would introduce rounding into a value used for a distance
     * comparison that decides someone's pay.
     *
     * clock_radius_m is nullable rather than defaulted so "never configured"
     * is distinguishable from "deliberately set to the default": an outlet
     * with no coordinates cannot enforce a geofence at all, and the clock-in
     * service has to know that rather than silently passing everyone.
     */
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('state');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedSmallInteger('clock_radius_m')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'clock_radius_m']);
        });
    }
};
