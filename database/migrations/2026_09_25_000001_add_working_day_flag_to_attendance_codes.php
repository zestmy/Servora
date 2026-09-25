<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which attendance marks are a day AT WORK, for allowances paid per working
 * day (a meal allowance, say).
 *
 * A flag on the code rather than another list of guesses, because codes are
 * per-company configurable: "OS" is out station in the default legend and
 * could be anything in somebody else's. Paid leave is deliberately not a
 * working day here — a meal allowance is for the meal eaten at work, and a
 * day on annual leave is paid by basic, not by this.
 *
 * Backfilled onto the codes the default legend treats as being at work:
 * Present (by system key, which cannot be renamed), Out Station, Training and
 * Meeting. Anything else a company wants counted, it ticks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_codes', function (Blueprint $table) {
            $table->boolean('counts_as_working_day')->default(false)->after('system_key');
        });

        DB::table('attendance_codes')
            ->where('system_key', 'present')
            ->orWhereIn(DB::raw('UPPER(TRIM(code))'), ['OS', 'TR', 'MTG'])
            ->update(['counts_as_working_day' => true]);
    }

    public function down(): void
    {
        Schema::table('attendance_codes', function (Blueprint $table) {
            $table->dropColumn('counts_as_working_day');
        });
    }
};
