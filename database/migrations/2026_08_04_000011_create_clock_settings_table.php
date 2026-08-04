<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per company: the rules a clock-in is judged against.
     *
     * Company-level rather than per-outlet. The lateness penalty is a pay
     * policy, and a company running two rates in two outlets of the same
     * business would have to defend that to the employee it costs. The
     * geofence, which genuinely IS per-location, lives on outlets instead.
     *
     * Every enforcement switch defaults to OFF and every money field to
     * zero. Running the migration must not start docking anyone's pay —
     * a manager has to turn each rule on deliberately.
     */
    public function up(): void
    {
        Schema::create('clock_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Minutes past the rostered start that are forgiven outright.
            $table->unsignedSmallInteger('grace_minutes')->default(5);

            // RM deducted per minute late, after grace. Zero = track lateness
            // but never charge for it, which is where every company starts.
            $table->decimal('late_rate_per_minute', 8, 2)->default(0);

            // Ceiling per shift, so one catastrophic morning cannot wipe out
            // a month of service charge. Null = uncapped.
            $table->decimal('late_cap_per_shift', 10, 2)->nullable();

            // How early a shift may be clocked into. Stops someone arriving
            // at 6am for a 2pm shift and banking a "not late" punch.
            $table->unsignedSmallInteger('early_window_minutes')->default(120);

            // Enforcement switches. When off, the corresponding check still
            // RUNS and is recorded on the punch — it just cannot flag it.
            $table->boolean('require_gps')->default(true);
            $table->boolean('require_face')->default(true);

            // Worst GPS accuracy (metres) still trusted for a geofence
            // decision. A 500m-accurate fix "inside" a 150m radius means
            // nothing, and indoor fixes are routinely that bad.
            $table->unsignedSmallInteger('max_accuracy_m')->default(100);

            /*
             * Euclidean distance between 128-float face descriptors below
             * which two faces are treated as the same person.
             *
             * 0.6 is the threshold face-api.js documents for its recognition
             * model. 0.5 is deliberately tighter: the cost of a false accept
             * here is buddy-punching that nobody catches, while a false
             * reject merely flags the punch for a manager to wave through.
             */
            $table->decimal('face_threshold', 4, 3)->default(0.500);

            // Write a Present mark into the attendance grid when a punch
            // passes. Off by default: a company mid-migration wants its
            // manually painted grid left alone until it trusts the clock.
            $table->boolean('mark_attendance')->default(false);

            // Let staff clock in off-site if they type a reason. The punch is
            // still flagged — this only decides whether it is refused outright.
            $table->boolean('allow_offsite_with_reason')->default(true);

            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clock_settings');
    }
};
