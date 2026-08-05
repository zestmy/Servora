<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-pool list of employees taking no share.
 *
 * A leaver stays on the pool covering the period they worked because they
 * earned those points — but that is a default, not always the agreement, so
 * it can be overridden for one person for one period. Kept on the pool rather
 * than the employee: excluding someone from June must not touch May.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_charge_periods', function (Blueprint $table) {
            $table->json('excluded_employees')->nullable()->after('special_deductions');
        });
    }

    public function down(): void
    {
        Schema::table('service_charge_periods', function (Blueprint $table) {
            $table->dropColumn('excluded_employees');
        });
    }
};
