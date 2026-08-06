<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave: the types a company recognises, what each employee is entitled to,
 * and the requests against that entitlement.
 *
 * THE TYPES ARE DATA, not an enum. Annual leave, replacement public holiday,
 * paternity, compassionate and whatever else a company grants all behave the
 * same way — a name, a yearly entitlement and an approval — and hardcoding the
 * list would mean a deploy every time someone adds "marriage leave".
 *
 * The flag that matters is `is_claimable`. A replacement public holiday is
 * sometimes PAID OUT WITH SALARY instead of taken as a day off; the entitlement
 * still has to be recorded and reported, but nobody may apply for it. That is a
 * property of the type, set by HR in leave settings, and it is the difference
 * between a balance someone can spend and one they cannot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('name');                       // Annual Leave
            $table->string('code', 20);                   // AL
            $table->text('description')->nullable();

            // Paid leave costs the employee nothing; unpaid leave is recorded
            // but will show on payroll as a deduction once that is wired up.
            $table->boolean('is_paid')->default(true);

            /*
             * Whether an employee may APPLY for it.
             *
             * False = the entitlement is settled another way — the common case
             * is a replacement public holiday paid out with salary. The balance
             * is still tracked and reported; it simply cannot be spent as a day
             * off, and the apply form says so rather than silently rejecting.
             */
            $table->boolean('is_claimable')->default(true);

            $table->boolean('requires_approval')->default(true);
            $table->boolean('allows_half_day')->default(true);

            // Starting entitlement for a new employee, in days. Per-employee
            // entitlement overrides it; this is only the default.
            $table->decimal('default_days', 5, 1)->default(0);

            // Unused days that may roll into next year, and how many.
            $table->boolean('carry_forward')->default(false);
            $table->decimal('carry_forward_cap', 5, 1)->nullable();

            $table->string('colour', 20)->default('slate');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();

            // Entitlement is per YEAR: last year's balance is a different fact
            // from this year's, and a report has to be able to ask for either.
            $table->unsignedSmallInteger('year');

            $table->decimal('entitled_days', 5, 1)->default(0);
            $table->decimal('carried_days', 5, 1)->default(0);
            // Days granted outside the normal entitlement — a long-service
            // award, a goodwill day. Kept separate so the entitlement itself
            // still reads as the policy figure.
            $table->decimal('adjustment_days', 5, 1)->default(0);
            $table->string('notes', 255)->nullable();

            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year'], 'leave_entitlements_unique');
            $table->index(['company_id', 'year']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            /*
             * Days is STORED, not derived from the dates.
             *
             * A half day, a request spanning a rest day, and a company that
             * does not count weekends all make "end minus start" the wrong
             * answer. What was agreed is the number of days deducted from the
             * balance, so that is what is kept.
             */
            $table->decimal('days', 5, 1);
            $table->boolean('is_half_day')->default(false);
            $table->string('half_day_period', 10)->nullable();   // am | pm

            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');     // pending|approved|rejected|cancelled

            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_entitlements');
        Schema::dropIfExists('leave_types');
    }
};
