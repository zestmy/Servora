<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Switch off staff whose resignation date has already passed.
 *
 * Recording a resignation never touched `is_active`, so everyone marked
 * resigned so far is still flagged active and keeps appearing on attendance
 * grids, duty rosters and the service charge pool for periods long after they
 * left. From here on the employee form and `hr:apply-resignations` keep this
 * true; this is the one-off catch-up for rows already on file.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')
            ->where('is_active', true)
            ->where('employment_status', 'resigned')
            ->whereNotNull('employment_status_date')
            ->whereDate('employment_status_date', '<', now()->toDateString())
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        // Reactivating everyone would resurrect staff who were deactivated for
        // other reasons; the resignation dates are untouched, so re-running
        // `hr:apply-resignations` reproduces this state exactly.
    }
};
