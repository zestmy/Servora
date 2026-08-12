<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a service charge period keep the split it was calculated with.
 *
 * REPORTED AS: a 1–25 July pool calculated at RM556 a point was RM574 the next
 * time it was opened, with nobody having touched the pool. The pool had not
 * changed. Only the AMOUNT was ever stored — the split (total points, RM per
 * point, each person's share) was recomputed live from current employee data
 * on every view, so any edit to staff re-priced a period that had already been
 * closed. Ten employee records were edited that afternoon; one service point
 * left the divisor; 17,101.24 ÷ 30.75 is 556 and ÷ 29.75 is 574.
 *
 * "Save & Calculate" now saves the calculation. `distribution` holds the
 * result, and everything that reads a pool reads that instead of recomputing.
 *
 * THE SNAPSHOT HOLDS FIGURES, NOT PEOPLE. Rows are keyed by employee id and
 * carry only money and points; names, sections and outlets are looked up live
 * when it is rendered. A rename should show the new name — what must not move
 * is what somebody was paid.
 *
 * APPROVED PAYROLL RUNS ARE UNAFFECTED EITHER WAY. A run copies each person's
 * service charge onto its own line when it is generated, and an approved run
 * cannot be regenerated, so recalculating a pool afterwards cannot reach back
 * into money the company has already committed to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_charge_periods', function (Blueprint $table) {
            $table->json('distribution')->nullable()->after('excluded_employees');
            $table->timestamp('calculated_at')->nullable()->after('distribution');
            // Nulled rather than cascaded: who calculated a pool is part of its
            // history, and deleting a staff account must not quietly make a
            // frozen period look as though nobody ever signed off on it.
            $table->foreignId('calculated_by')->nullable()->after('calculated_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_charge_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('calculated_by');
            $table->dropColumn(['distribution', 'calculated_at']);
        });
    }
};
