<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employment TYPE, separate from employment STATUS: Local Malaysian, Direct
 * Hire Foreign Worker, Outsourcing Foreign Worker.
 *
 * Outsourcing used to be a status, so it could not coexist with any other: an
 * agency worker who left had to be either "Outsourcing" or "Resigned", and a
 * resigned agency head either stayed on every active list or vanished from
 * every outsourced one. Who the person is employed as (the type) and where
 * they stand in their employment (the status) are two separate facts.
 *
 * Backfill:
 *   status outsourcing        → foreign_outsourcing, status cleared (it never
 *                               had a date, and guessing "confirmed" would
 *                               invent a confirmation nobody recorded)
 *   nationality Malaysian     → local
 *   any other nationality     → foreign_direct
 *   no nationality recorded   → left null, for HR to set — a guess here
 *                               decides who is treated as foreign.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('employment_type', 30)->nullable()->after('employment_status');
            $table->index(['company_id', 'employment_type']);
        });

        DB::table('employees')
            ->where('employment_status', 'outsourcing')
            ->update([
                'employment_type'        => 'foreign_outsourcing',
                'employment_status'      => null,
                'employment_status_date' => null,
            ]);

        DB::table('employees')
            ->whereNull('employment_type')
            ->whereRaw("LOWER(TRIM(nationality)) IN ('malaysian', 'malaysia')")
            ->update(['employment_type' => 'local']);

        DB::table('employees')
            ->whereNull('employment_type')
            ->whereNotNull('nationality')
            ->where('nationality', '!=', '')
            ->update(['employment_type' => 'foreign_direct']);
    }

    public function down(): void
    {
        // Only those with no other status can go back without losing one.
        DB::table('employees')
            ->where('employment_type', 'foreign_outsourcing')
            ->whereNull('employment_status')
            ->update(['employment_status' => 'outsourcing']);

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'employment_type']);
            $table->dropColumn('employment_type');
        });
    }
};
