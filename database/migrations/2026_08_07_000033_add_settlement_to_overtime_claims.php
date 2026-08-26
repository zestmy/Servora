<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How an approved overtime claim gets settled: paid, or taken as time off.
 *
 * A SEPARATE AXIS from `status`, not a fourth status, and that is the whole
 * design decision here. `status` drives the approval workflow — draft,
 * submitted, approved, rejected — and something like an 'approved_time_off'
 * status would have to be added to every `where('status', 'approved')` in the
 * codebase, of which there are many across the claims screen, the balance
 * service, payroll and the summary queries. Miss one and a time-off claim
 * either disappears from a total or gets paid twice.
 *
 * Two columns instead: the claim is approved, and separately it is destined
 * for payroll or for time off. Everything that already asks "is this
 * approved?" keeps working untouched.
 *
 * Defaults to 'payroll', which is what every existing claim is.
 *
 * WHAT IT ACTUALLY CHANGES. Approved-and-unpaid overtime is ALREADY available
 * to take as time off — see TimeOffBalance::unpaidClaims() — so this does not
 * open that door, it stops the door being closed. A payroll-destined claim
 * loses its availability the moment a payroll run is approved, because
 * settleOvertime() stamps paid_at on it. A time-off claim is never settled and
 * never priced, so it stays available until it is actually taken.
 *
 * SINCE CHANGED (2026-08-27), and the paragraph above is left as written
 * because it is why the column was added. The time-off balance no longer
 * counts payroll-destined claims at all: leaving them in made this flag
 * advisory, since overtime explicitly marked to be PAID could be taken as
 * leave instead. See TimeOffBalance::unpaidClaims() for the rule as it now
 * stands.
 *
 * The two places that must respect it are therefore CompensationSummary (do
 * not pay it) and PayrollRun::settleOvertime() (do not mark it paid). The
 * second is the dangerous one: without it, approving a payroll run would stamp
 * paid_at on these claims and silently delete the very balance this feature
 * exists to protect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_claims', function (Blueprint $table) {
            $table->enum('settlement', ['payroll', 'time_off'])
                ->default('payroll')
                ->after('status');

            // Payroll and the balance service both filter on it alongside
            // status, over a table that grows a row per person per OT night.
            $table->index(['company_id', 'status', 'settlement'], 'ot_claims_settlement_index');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_claims', function (Blueprint $table) {
            $table->dropIndex('ot_claims_settlement_index');
            $table->dropColumn('settlement');
        });
    }
};
