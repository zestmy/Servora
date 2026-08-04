<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per punch. Append-only in spirit: a wrong punch is reviewed
     * and overridden, never edited away, because this table is the evidence
     * behind a pay deduction and has to survive being questioned.
     *
     * Every check records BOTH its input and its verdict — the raw distance
     * in metres as well as within_geofence, the match score as well as
     * face_verified. Storing only the verdict would make an argument three
     * weeks later unanswerable, and the thresholds that produced it are
     * settings that may since have changed.
     */
    public function up(): void
    {
        Schema::create('clock_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // The shift this punch was matched to, if any. Nullable: people
            // do turn up on days they were never rostered.
            $table->foreignId('roster_entry_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('type', ['in', 'out']);

            /*
             * The business day this punch belongs to — NOT simply the date
             * part of happened_at. A 1am finish on an overnight shift belongs
             * to the day the shift started, and payroll periods are cut on
             * this column.
             */
            $table->date('work_date');
            $table->dateTime('happened_at');

            // Where the phone said it was. Nullable throughout: permission
            // can be refused, and a refusal is itself a fact worth keeping.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('accuracy_m')->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->boolean('within_geofence')->default(false);

            /*
             * Euclidean descriptor distance — LOWER is a better match. Stored
             * as the raw figure rather than a percentage so it stays
             * comparable against face_threshold, which is what actually
             * decided the verdict.
             */
            $table->decimal('face_distance', 5, 4)->nullable();
            $table->boolean('face_verified')->default(false);
            $table->string('selfie_path')->nullable();

            // Minutes past the rostered start BEFORE grace is applied, and
            // the chargeable minutes after it. Keeping both means the grace
            // setting can change without rewriting history.
            $table->unsignedSmallInteger('minutes_late')->default(0);
            $table->unsignedSmallInteger('chargeable_late_minutes')->default(0);
            $table->decimal('penalty_amount', 10, 2)->default(0);

            /*
             * verified — every enabled check passed, nothing to look at.
             * flagged  — recorded, but a manager has to decide.
             * approved — a manager accepted a flagged punch.
             * rejected — a manager threw it out; it stops counting entirely.
             */
            $table->enum('status', ['verified', 'flagged', 'approved', 'rejected'])->default('flagged');

            // Machine-readable reasons behind a flag, e.g. ["outside_geofence"].
            $table->json('flags')->nullable();

            // Typed by the employee when clocking in from off-site.
            $table->text('reason')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // Set when a manager overrides the computed lateness, so the
            // original figure above stays visible next to what was charged.
            $table->unsignedSmallInteger('override_late_minutes')->nullable();

            $table->string('device_label')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->ipAddress('ip_address')->nullable();

            $table->timestamps();

            // The service charge run: every punch for a company in a period.
            $table->index(['company_id', 'work_date']);
            // The staff app's "what have I done today", and pairing an out
            // with its in.
            $table->index(['employee_id', 'work_date', 'type']);
            // The review queue.
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clock_events');
    }
};
