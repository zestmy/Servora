<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compensation: what an employee is paid beyond basic salary, and the trail of
 * how basic salary got to where it is.
 *
 * `employees.basic_salary` / `pay_type` stay where they are — they are read by
 * the Employees list, the attendance grid, the exports and the CSV import, and
 * moving them would be churn for no gain. This adds the parts around them:
 * recurring allowances and deductions, and an approved history of changes to
 * the basic figure itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The company's catalogue of allowances and deductions.
        Schema::create('pay_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description')->nullable();
            // allowance adds, deduction subtracts. One table rather than two,
            // because everything else about them is identical and a single
            // list is what a payroll clerk actually reads.
            $table->string('kind', 20)->default('allowance');
            // fixed = a ringgit figure; percent_basic = a share of basic salary,
            // which is how most statutory-style items are expressed.
            $table->string('calculation', 20)->default('fixed');
            $table->decimal('default_amount', 12, 2)->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        // What each employee actually gets, and from when.
        Schema::create('employee_pay_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            // Dated rather than a single current row: a summary for March must
            // report what March was owed, not what the employee gets today.
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
            $table->index(['company_id', 'pay_component_id']);
        });

        // Every change to basic salary, requested and approved.
        Schema::create('salary_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // The "from" figures are frozen at request time. Reading them back
            // off the employee later would show today's salary on both sides
            // once a second revision lands.
            $table->decimal('from_amount', 12, 2)->nullable();
            $table->string('from_pay_type', 10)->nullable();
            $table->decimal('to_amount', 12, 2);
            $table->string('to_pay_type', 10);
            $table->date('effective_on');
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            // Set when an approved revision has been written onto the employee,
            // so a future-dated raise can be applied on its date and not twice.
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'effective_on']);
        });

        // Per-company overtime rates. Defaults follow the Malaysian Employment
        // Act: ordinary rate of pay = monthly / 26, hourly = ORP / 8, and
        // 1.5x / 2.0x / 3.0x for a normal day, rest day and public holiday.
        Schema::create('compensation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->decimal('ot_normal_multiplier', 5, 2)->default(1.50);
            $table->decimal('ot_rest_day_multiplier', 5, 2)->default(2.00);
            $table->decimal('ot_public_holiday_multiplier', 5, 2)->default(3.00);
            $table->unsignedSmallInteger('monthly_working_days')->default(26);
            $table->decimal('daily_working_hours', 4, 2)->default(8.00);
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensation_settings');
        Schema::dropIfExists('salary_revisions');
        Schema::dropIfExists('employee_pay_components');
        Schema::dropIfExists('pay_components');
    }
};
