<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A labour transfer line is priced on one of three bases:
 *
 *   daily   — days x daily rate, plus approved OT in the dates (the original)
 *   hourly  — hours x hourly rate, plus approved OT: a few hours' help, not a day
 *   ot_only — approved OT in the dates and nothing else: the person did their
 *             own shift at home and the overtime was worked for someone else
 *
 * Existing lines become 'daily', which is what they always were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labour_cost_transfer_lines', function (Blueprint $table) {
            $table->string('basis', 10)->default('daily')->after('date_end');
            $table->decimal('hours', 8, 2)->default(0)->after('days');
            $table->decimal('hourly_rate', 12, 4)->default(0)->after('daily_rate');
        });
    }

    public function down(): void
    {
        Schema::table('labour_cost_transfer_lines', function (Blueprint $table) {
            $table->dropColumn(['basis', 'hours', 'hourly_rate']);
        });
    }
};
