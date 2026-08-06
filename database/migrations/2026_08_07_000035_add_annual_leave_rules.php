<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two company-wide rules for annual leave.
 *
 *   annual_prorated               Entitlement earned across the year in
 *                                 proportion to service, or given in full from
 *                                 day one.
 *
 *   annual_requires_confirmation  Whether it may be taken while somebody is
 *                                 still on probation.
 *
 * BOTH DEFAULT TO THE MORE GENEROUS SETTING — full entitlement, usable during
 * probation — because that is what the app does today. A migration must not
 * quietly reduce anybody's leave balance or block an application that would
 * have been allowed yesterday; a company that wants either rule turns it on
 * deliberately.
 *
 * WHY is_annual EXISTS. Leave types are user-editable — name, code and all —
 * so there is nothing on them today that says which one these rules govern,
 * and matching on the string 'AL' would break the moment somebody renames it
 * or runs a company in Malay. `is_replacement_holiday` already establishes the
 * pattern for "this type is special", and this follows it exactly.
 *
 * It is backfilled from the seeded code rather than assumed: only a type
 * actually called AL becomes the annual one, and a company that never had that
 * code gets no annual type until somebody ticks it — which is correct, because
 * guessing wrong here would silently pro-rate the wrong entitlement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_settings', function (Blueprint $table) {
            $table->boolean('annual_prorated')->default(false)->after('rph_expiry_days');
            $table->boolean('annual_requires_confirmation')->default(false)->after('annual_prorated');
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('is_annual')->default(false)->after('is_replacement_holiday');
        });

        // The seeded annual type, per company. Deliberately narrow: `code`,
        // not a LIKE on the name, so "Annual Leave (Senior)" or a translated
        // label is not swept in by accident.
        DB::table('leave_types')->whereRaw('UPPER(TRIM(code)) = ?', ['AL'])->update(['is_annual' => true]);
    }

    public function down(): void
    {
        Schema::table('leave_settings', function (Blueprint $table) {
            $table->dropColumn(['annual_prorated', 'annual_requires_confirmation']);
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('is_annual');
        });
    }
};
