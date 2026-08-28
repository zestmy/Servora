<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A qualifying period for a service charge pool, counted in working days.
 *
 * A pool is shared by service points, and points are an entitlement somebody
 * carries whether they worked the period or not. That is fine for a full
 * month and wrong at the edges: a joiner who started on the 27th and a leaver
 * who went on the 3rd each hold their full points and each take a full share,
 * diluting everybody who worked the other 28 days.
 *
 * min_working_days is the house rule for that — "you must have worked at
 * least N days in this period to be in this pool". Zero, the default, means
 * no minimum, which is what every pool saved before this migration had, so
 * nothing already calculated moves.
 *
 * PER POOL, NOT PER COMPANY. It sits beside mc_percent and abs_percent for
 * the same reason those do: it is a term of one period's split, and a rule
 * tightened in August must not silently re-price July.
 *
 * What counts as a working day is ServiceChargePeriod::workingDayCounts() —
 * in short, a day with an attendance mark that is not UNRECORDED, not a day
 * off and not an absence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_charge_periods', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_working_days')->default(0)->after('abs_percent');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('service_charge_periods', 'min_working_days')) {
            Schema::table('service_charge_periods', fn (Blueprint $t) => $t->dropColumn('min_working_days'));
        }
    }
};
