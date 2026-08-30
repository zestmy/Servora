<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Last seen", which is the most a web app can honestly offer about where
 * somebody is.
 *
 * WHAT THIS IS NOT. It is not tracking. A PWA cannot run in the background —
 * the Geolocation API does not exist in a service worker in any browser, iOS
 * suspends an installed app the instant it is backgrounded, and Chrome's
 * periodic background sync is throttled to hours and cannot reach geolocation
 * either. Nothing that ships in a browser can tell you where an employee is
 * while their phone is in their pocket, and a column promising otherwise
 * would be a lie told by a schema.
 *
 * What it CAN do is record where the phone was the last time its owner opened
 * the Staff Portal. That answers a real question a manager asks — "is anyone
 * from the morning shift still around?" — and it answers it as an
 * approximation with a timestamp attached, which is why every reader here is
 * required to render the age beside the place. "At Bangsar, 4 minutes ago" is
 * useful. "At Bangsar" on its own is the same sentence with the honesty
 * removed.
 *
 * ONE ROW PER PERSON, OVERWRITTEN. Deliberately not a history table. A trail
 * of pings is a movement profile — a different thing to hold, under PDPA and
 * under any reasonable reading of what staff agreed to — and nothing on the
 * screens wants it. Punch locations already live on clock_events, where they
 * have a purpose: proving attendance at a moment somebody was paid for.
 *
 * OFF BY DEFAULT, per company. See clock_settings.location_heartbeat. A
 * company switching this on is making a decision about its own staff and
 * ought to have to make it; migrating everybody into it overnight is how you
 * end up collecting locations nobody was told about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_settings', function (Blueprint $table) {
            /*
             * OFF is the honest default. The client also refuses to prompt for
             * location on the strength of this alone — see resources/js/
             * heartbeat.js — so switching it on for a company whose staff have
             * never granted the permission changes nothing until they do, at a
             * punch, where the ask has an obvious reason attached to it.
             */
            $table->boolean('location_heartbeat')->default(false)->after('resolve_addresses');
        });

        Schema::table('employees', function (Blueprint $table) {
            /*
             * NULLABLE AND SEPARATE FROM THE COORDINATES, because the two
             * answer different questions and one arrives far more often than
             * the other. Every ping sets last_seen_at; only a ping from a
             * phone that has already granted location sets the rest. So a
             * reader must handle "seen 3 minutes ago, no location" — which is
             * the ordinary state for anybody who declined, and is still worth
             * showing.
             */
            $table->timestamp('last_seen_at')->nullable()->index();

            // decimal:7 matches clock_events and outlets — ~11mm, well past
            // what any handset knows, and the same precision everywhere means
            // no rounding surprises when the two are compared.
            $table->decimal('last_seen_latitude', 10, 7)->nullable();
            $table->decimal('last_seen_longitude', 10, 7)->nullable();

            // The fix's own claimed error, in metres. Kept because a 2km fix
            // and a 12m fix are not the same statement, and a screen that
            // renders both identically invites somebody to act on the first.
            $table->unsignedSmallInteger('last_seen_accuracy_m')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clock_settings', function (Blueprint $table) {
            $table->dropColumn('location_heartbeat');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'last_seen_at', 'last_seen_latitude',
                'last_seen_longitude', 'last_seen_accuracy_m',
            ]);
        });
    }
};
