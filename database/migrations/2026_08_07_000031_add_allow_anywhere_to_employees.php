<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff whose job is not at the outlet.
 *
 * Area managers touring four branches, drivers, a crew working an offsite
 * event, someone on a supplier visit. Today every one of their punches lands
 * outside the geofence — so they are either refused outright, or made to type
 * a reason twice a day for a fact about their job that has not changed, and
 * either way the punch is flagged and lands in the review queue.
 *
 * That is the queue's real enemy. A manager who opens it every morning to
 * find the same four people flagged for the same unremarkable reason stops
 * opening it, and the punches that genuinely could not be verified are buried
 * underneath. An exemption that is granted once, by name, keeps the queue
 * meaning something.
 *
 * A PLAIN BOOLEAN, unlike allow_byod next door, and the difference is worth
 * being explicit about. allow_byod is nullable because there is a per-outlet
 * rule for it to inherit — punch_mode — and null means "whatever the outlet
 * says". There is no per-outlet "may people clock in from anywhere" rule for
 * this to inherit; the geofence is a property of the place, and this is a
 * property of the person's job. Nothing to inherit means no third state.
 *
 * Defaults FALSE. It exempts somebody from the only check that says they were
 * at work, so it has to be granted deliberately, one person at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('allow_anywhere')->default(false)->after('allow_byod');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('allow_anywhere');
        });
    }
};
