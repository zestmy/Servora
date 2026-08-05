<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Street-level addresses for clock-in punches.
 *
 * THE ADDRESS IS STORED ON THE EVENT, not looked up when someone opens the
 * screen. A manager scrolling a month of punches must not fire a hundred
 * requests at a third party, and an address resolved a year ago is still the
 * address that punch happened at — freshness is not a property this data has.
 *
 * geocode_cache is keyed by coordinates rounded to 5 decimal places (~1 m).
 * Staff punch from the same doorway every day, so one lookup serves hundreds
 * of punches; on a free provider that is the difference between courteous and
 * abusive, and on a paid one it is the bill.
 *
 * Off by default. It sends staff coordinates to an external service, which is
 * a decision for the company to make on the settings screen, not a default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_events', function (Blueprint $table) {
            $table->string('address', 500)->nullable()->after('within_geofence');
            // Distinguishes "not looked up yet" from "looked up, nothing found"
            // — without it a failed lookup would be retried forever.
            $table->timestamp('address_resolved_at')->nullable()->after('address');
        });

        Schema::table('clock_settings', function (Blueprint $table) {
            $table->boolean('resolve_addresses')->default(false)->after('allow_offsite_with_reason');
        });

        Schema::create('geocode_cache', function (Blueprint $table) {
            $table->id();
            $table->decimal('latitude', 10, 5);
            $table->decimal('longitude', 10, 5);
            $table->string('address', 500)->nullable();
            $table->string('provider', 30);
            $table->timestamps();

            $table->unique(['latitude', 'longitude', 'provider'], 'geocode_cache_point_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocode_cache');

        if (Schema::hasColumn('clock_settings', 'resolve_addresses')) {
            Schema::table('clock_settings', fn (Blueprint $t) => $t->dropColumn('resolve_addresses'));
        }

        foreach (['address', 'address_resolved_at'] as $column) {
            if (Schema::hasColumn('clock_events', $column)) {
                Schema::table('clock_events', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
