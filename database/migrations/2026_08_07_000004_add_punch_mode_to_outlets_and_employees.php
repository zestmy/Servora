<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an outlet's staff are expected to punch, and who is excused from it.
 *
 * The problem this solves is that running a kiosk AND phones side by side at
 * one outlet quietly makes the phone the default — not through anybody's bad
 * faith, but because the phone is already in your hand and there are three
 * people at the tablet. Left alone, the weaker path wins by convenience and
 * the kiosk becomes decorative.
 *
 * So the choice does not live at the door, it lives in two places:
 *
 *   outlets.punch_mode   the RULE for everybody at that outlet.
 *   employees.allow_byod a named EXCEPTION to it.
 *
 * That is the whole design. Area managers, drivers, and crew working an
 * offsite event genuinely need their phones; everybody else uses the tablet
 * that is three metres away. Turning that from "whatever is easiest" into
 * "something a manager granted to a named person" is the entire control, and
 * it costs one nullable column.
 *
 * DEFAULTS CHANGE NOTHING. Every existing outlet becomes byod_only, which is
 * exactly what it is today — the kiosk did not exist until this release. An
 * outlet starts using its kiosk when somebody switches it over, not because a
 * migration ran overnight and locked a kitchen out of its own clock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            /*
             * kiosk_only — punch on the outlet tablet. Somebody with the BYOD
             *              exception may still use their phone; everybody else
             *              is recorded and flagged, never refused. A refusal
             *              here would mean a flat battery costs a day's pay.
             * byod_only  — no kiosk here. What every outlet is today.
             * both       — deliberately equal. For an outlet mid-rollout, or
             *              one whose kiosk covers only half the building.
             */
            $table->enum('punch_mode', ['kiosk_only', 'byod_only', 'both'])
                ->default('byod_only')
                ->after('clock_radius_m');
        });

        Schema::table('employees', function (Blueprint $table) {
            /*
             * NULL means "whatever the outlet says", and that is the important
             * value — it is what almost every row will hold, and it lets an
             * outlet change its mind without touching its staff one by one.
             *
             * True and false are both overrides: true excuses somebody from a
             * kiosk_only outlet, false pins somebody to the tablet even where
             * the outlet allows both.
             */
            $table->boolean('allow_byod')->nullable()->after('label_pin');
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn('punch_mode');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('allow_byod');
        });
    }
};
