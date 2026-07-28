<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-employee salary. Stored alongside pay_type so outsourced / part-time
     * staff who are paid daily or hourly are represented honestly rather than
     * being forced into a monthly figure.
     *
     * Both columns are pay-sensitive: reading them is gated on the
     * hr.compensation permission (see the migration that creates it).
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('basic_salary', 12, 2)->nullable()->after('service_points_entitlement');
            $table->string('pay_type', 10)->nullable()->after('basic_salary');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['basic_salary', 'pay_type']);
        });
    }
};
