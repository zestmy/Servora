<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SKBBK — Skim Kemalangan Bukan Bencana Kerja, branded LINDUNG 24 Jam.
 *
 * A PERKESO scheme under the Employees' Social Security Act 1969 covering
 * accidents OUTSIDE work, launched 1 June 2026. Three things about it drive
 * every decision below, and all three differ from the schemes already here:
 *
 *  1. THE EMPLOYEE PAYS THE WHOLE OF IT. PERKESO's own wording is "caruman
 *     ditanggung sepenuhnya oleh pekerja". There is no employer share, so
 *     there is no employer column and nothing joins cost-to-company. It is the
 *     mirror image of the HRD Corp levy, which is employer-only.
 *
 *  2. THE RATE IS PHASED: 0.75% for the first two years, 1.0% for the next
 *     three, 1.25% from year six. It is stored as a plain editable rate rather
 *     than a schedule of dates, because the phase boundaries are a policy that
 *     may move and a hard-coded calendar would quietly deduct the wrong
 *     percentage the month it changed. It defaults to 0.75% — the rate in
 *     force now — and the settings screen says to check it against PERKESO.
 *
 *  3. IT IS NO LONGER MANDATORY FOR LOCALS. A Cabinet decision on 10 July 2026
 *     let local employees opt out from 14 July by filing a liability release;
 *     foreign workers remain mandatory under immigration provisions. So this
 *     cannot be a company-wide on/off — it has to be answerable per person,
 *     which is what the nullable per-employee column is for.
 *
 * A PERCENTAGE OF CAPPED WAGES, not the published band table — the same
 * approximation StatutoryCalculator already makes for SOCSO and EIS, and
 * already warns about in its own docblock. It lands within sen of the table
 * and must be reconciled against PERKESO before a return is submitted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statutory_settings', function (Blueprint $table) {
            $table->boolean('skbbk_enabled')->default(false)->after('eis_ceiling');
            // 0.75% is Phase 1, in force from June 2026.
            $table->decimal('skbbk_employee_rate', 5, 2)->default(0.75)->after('skbbk_enabled');
            // Wages above this do not attract a larger contribution. 6,000 is
            // the ceiling reported at launch; left editable because a ceiling
            // is exactly the kind of figure that gets revised.
            $table->decimal('skbbk_ceiling', 10, 2)->default(6000)->after('skbbk_employee_rate');
        });

        Schema::table('employee_statutory_profiles', function (Blueprint $table) {
            /*
             * NULLABLE ON PURPOSE — three states, not two.
             *
             *   null   follow the rule: mandatory for a foreign worker,
             *          and not taken from a local, who must opt in
             *   true   this person contributes (a local who stayed in)
             *   false  this person does not (an explicit opt-out on record)
             *
             * A plain boolean would collapse "nobody has been asked yet" into
             * "they said no", and the difference matters: one is a decision
             * with a signed liability release behind it and the other is a
             * gap in the paperwork.
             */
            $table->boolean('skbbk_enabled')->nullable()->after('eis_enabled');
        });

        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->decimal('skbbk', 12, 2)->default(0)->after('eis_employer');
        });
    }

    public function down(): void
    {
        Schema::table('statutory_settings', function (Blueprint $table) {
            $table->dropColumn(['skbbk_enabled', 'skbbk_employee_rate', 'skbbk_ceiling']);
        });

        Schema::table('employee_statutory_profiles', function (Blueprint $table) {
            $table->dropColumn('skbbk_enabled');
        });

        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->dropColumn('skbbk');
        });
    }
};
