<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time off taken against overtime that has not been paid out.
 *
 * THE RULE THIS EXISTS TO ENFORCE: an hour of overtime is either PAID or TAKEN
 * OFF, never both. Approved overtime that the company has not yet paid is a
 * balance the employee may draw down as hours away from work; once payroll has
 * paid it, it is gone and cannot be taken as time off.
 *
 * So the overtime claim gains two things: whether it has been paid, and how
 * many of its hours have been consumed as time off. Payroll pays
 * `total_ot_hours - hours_taken_off`, which is what stops the double count.
 *
 * The allocation table is the audit trail. "Which overtime did my Tuesday
 * afternoon come out of" is a question people genuinely ask, and a running
 * balance alone cannot answer it. Allocation is oldest-claim-first, so the
 * overtime nearest to expiring or to being paid is used up first.
 *
 * A DAY IS NOT EIGHT HOURS FOR EVERYONE. Contracts here run 7.5, 9 and 11
 * hours, so time off is requested in HOURS and the employee's own working day
 * is only used to show what that means in days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_claims', function (Blueprint $table) {
            // Paid by the company, and when. Null = still owed, and therefore
            // still available to be taken as time off.
            $table->timestamp('paid_at')->nullable()->after('approved_at');
            $table->foreignId('paid_in_run_id')->nullable()->after('paid_at')
                ->constrained('payroll_runs')->nullOnDelete();
            $table->foreignId('marked_paid_by')->nullable()->after('paid_in_run_id')
                ->constrained('users')->nullOnDelete();

            // Hours of this claim already consumed as time off. Payroll pays
            // the remainder; this is the only thing preventing a double count.
            $table->decimal('hours_taken_off', 6, 2)->default(0)->after('total_ot_hours');
        });

        Schema::table('employees', function (Blueprint $table) {
            // Contract working day, in hours. Null follows the company default
            // — most staff do, and a per-person copy of the same number is a
            // second thing to keep in step.
            $table->decimal('daily_working_hours', 5, 2)->nullable()->after('break_minutes');
        });

        Schema::create('time_off_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->date('off_date');
            // Hours, not days: a 7.5-hour day and an 11-hour day are both a
            // "day off" and they are not the same amount of overtime.
            $table->decimal('hours', 6, 2);

            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');   // pending|approved|rejected|cancelled

            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'off_date']);
        });

        Schema::create('time_off_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_off_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('overtime_claim_id')->constrained()->cascadeOnDelete();
            $table->decimal('hours', 6, 2);
            $table->timestamps();

            // One row per (request, claim) pair — a request draws at most once
            // from any single claim.
            $table->unique(['time_off_request_id', 'overtime_claim_id'], 'time_off_alloc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_off_allocations');
        Schema::dropIfExists('time_off_requests');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('daily_working_hours');
        });

        Schema::table('overtime_claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_in_run_id');
            $table->dropConstrainedForeignId('marked_paid_by');
            $table->dropColumn(['paid_at', 'hours_taken_off']);
        });
    }
};
