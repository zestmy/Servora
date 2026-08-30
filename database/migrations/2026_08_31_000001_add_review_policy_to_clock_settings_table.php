<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which flags actually send a punch to a manager.
 *
 * Until now that list was a constant — ClockEvent::NON_REVIEWABLE_FLAGS named
 * the three exceptions and everything else queued for a human. That is a
 * defensible default and a poor rule, because the cost of a review is paid by
 * a company whose circumstances the constant knows nothing about. An outlet
 * with no roster built flags every punch `no_shift`; one in a basement flags
 * every punch `weak_location`; one that switched off face enrolment flags
 * every punch `not_enrolled`. None of those is a decision anybody is making,
 * and a queue full of them is a queue nobody reads — which quietly costs the
 * flags that DO matter the attention they were raised for.
 *
 * So the exceptions list becomes a setting. Stored as the SKIP list rather
 * than the review list for two reasons:
 *
 *   NULL means "the default", so every existing company keeps exactly today's
 *   behaviour without this migration having to write a row for each of them.
 *
 *   A flag added in a later release is reviewed by default, because it is
 *   absent from everybody's skip list. The safe direction: a new check starts
 *   by asking, and a company that does not want to be asked says so. Storing
 *   the review list instead would silently ignore every check added after the
 *   day a company last opened this screen.
 *
 * Nothing here changes what is RECORDED. Every flag still lands in
 * clock_events.flags whether or not it routes to a manager, so a punch that
 * was outside the geofence still says so on the record, in the report, and to
 * anybody who goes looking six weeks later. This governs the queue, not the
 * evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_settings', function (Blueprint $table) {
            $table->json('auto_approve_flags')->nullable()->after('kiosk_allow_pin');
        });
    }

    public function down(): void
    {
        Schema::table('clock_settings', function (Blueprint $table) {
            $table->dropColumn('auto_approve_flags');
        });
    }
};
