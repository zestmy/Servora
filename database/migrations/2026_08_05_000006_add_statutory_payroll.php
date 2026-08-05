<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Malaysian statutory deductions: EPF, SOCSO, EIS and PCB.
 *
 * EVERY RATE IS A SETTING, not a constant. These are legislated numbers that
 * change by ministerial order — the EPF employer rate, the SOCSO and EIS
 * ceilings and the income tax bands have all moved in recent years — and a
 * company must be able to correct one on the morning it changes rather than
 * wait for a deploy. The seeded values are a starting point that the company
 * confirms on the settings screen, which records who confirmed them and when.
 *
 * What is per-EMPLOYEE lives on employee_statutory_profiles: their scheme
 * numbers, whether each contribution applies to them at all, and the PCB
 * inputs (category, children, zakat) that no company-wide default can answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // EPF and SOCSO rates both change at 60, so age is not optional
            // information once statutory deductions are switched on.
            $table->date('date_of_birth')->nullable()->after('join_date');
        });

        Schema::table('pay_components', function (Blueprint $table) {
            // Not every allowance is "wages". Travelling and meal allowances
            // are commonly outside EPF and SOCSO wages while still being part
            // of gross pay, so each component says what it counts towards.
            $table->boolean('epf_applicable')->default(true)->after('is_taxable');
            $table->boolean('socso_applicable')->default(true)->after('epf_applicable');
        });

        Schema::create('statutory_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->boolean('epf_enabled')->default(true);
            $table->boolean('socso_enabled')->default(true);
            $table->boolean('eis_enabled')->default(true);
            $table->boolean('pcb_enabled')->default(false);

            // EPF: employer rate steps down above a wage threshold, and both
            // sides change at 60.
            $table->decimal('epf_employee_rate', 5, 2)->default(11.00);
            $table->decimal('epf_employer_rate_low', 5, 2)->default(13.00);
            $table->decimal('epf_employer_rate_high', 5, 2)->default(12.00);
            $table->decimal('epf_wage_threshold', 10, 2)->default(5000.00);
            $table->decimal('epf_employee_rate_senior', 5, 2)->default(0.00);
            $table->decimal('epf_employer_rate_senior', 5, 2)->default(4.00);
            $table->decimal('epf_employee_rate_foreign', 5, 2)->default(2.00);
            $table->decimal('epf_employer_rate_foreign', 5, 2)->default(2.00);
            $table->unsignedSmallInteger('senior_age')->default(60);

            $table->decimal('socso_employee_rate', 5, 2)->default(0.50);
            $table->decimal('socso_employer_rate', 5, 2)->default(1.75);
            // Over 60 / first registered after 55: employer-only, lower rate.
            $table->decimal('socso_employer_rate_senior', 5, 2)->default(1.25);
            $table->decimal('socso_ceiling', 10, 2)->default(6000.00);

            $table->decimal('eis_employee_rate', 5, 2)->default(0.20);
            $table->decimal('eis_employer_rate', 5, 2)->default(0.20);
            $table->decimal('eis_ceiling', 10, 2)->default(6000.00);
            $table->unsignedSmallInteger('eis_max_age')->default(60);

            // PCB inputs. Bands and reliefs are json so a budget change is an
            // edit, not a schema migration.
            $table->json('pcb_tax_bands')->nullable();
            $table->decimal('pcb_relief_individual', 10, 2)->default(9000.00);
            $table->decimal('pcb_relief_epf_cap', 10, 2)->default(4000.00);
            $table->decimal('pcb_relief_spouse', 10, 2)->default(4000.00);
            $table->decimal('pcb_relief_child', 10, 2)->default(2000.00);
            // The individual rebate for a small chargeable income. Without it
            // the calculation deducts tax from low-paid staff who owe none,
            // which is the single most likely way to get this visibly wrong.
            $table->decimal('pcb_rebate_amount', 10, 2)->default(400.00);
            $table->decimal('pcb_rebate_threshold', 10, 2)->default(35000.00);

            // Who signed the numbers off, so nobody relies on a seeded guess.
            $table->timestamp('rates_confirmed_at')->nullable();
            $table->foreignId('rates_confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->unique('company_id');
        });

        Schema::create('employee_statutory_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->string('epf_number', 30)->nullable();
            $table->string('socso_number', 30)->nullable();
            $table->string('income_tax_number', 30)->nullable();

            // EPF rules differ for non-citizens, so this is not cosmetic.
            $table->boolean('is_malaysian')->default(true);

            $table->boolean('epf_enabled')->default(true);
            $table->boolean('socso_enabled')->default(true);
            $table->boolean('eis_enabled')->default(true);
            $table->boolean('pcb_enabled')->default(true);

            // An employee may elect to contribute above the statutory rate.
            $table->decimal('epf_employee_rate_override', 5, 2)->nullable();

            // PCB inputs no company-wide default can answer.
            $table->string('pcb_category', 40)->default('single'); // single | spouse_not_working | spouse_working
            $table->unsignedSmallInteger('children')->default(0);
            $table->decimal('monthly_zakat', 10, 2)->default(0);
            $table->decimal('annual_other_relief', 10, 2)->default(0);

            $table->timestamps();
            $table->unique('employee_id');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_statutory_profiles');
        Schema::dropIfExists('statutory_settings');

        foreach (['epf_applicable', 'socso_applicable'] as $column) {
            if (Schema::hasColumn('pay_components', $column)) {
                Schema::table('pay_components', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }

        if (Schema::hasColumn('employees', 'date_of_birth')) {
            Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('date_of_birth'));
        }
    }
};
