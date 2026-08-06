<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff whose overtime is always taken as time off rather than paid.
 *
 * A DEFAULT, not a lock. The decision still lives on the claim — see
 * overtime_claims.settlement — and an approver can still pay a one-off in
 * cash when it suits. This only decides which way the approval screen leans,
 * so nobody has to remember that this particular person is on time-off terms
 * every time one of their claims comes up.
 *
 * That distinction matters for history: a claim approved for payroll last
 * month must stay a payroll claim even if the person is switched to time-off
 * terms today. Settling how an existing claim is paid from a column on the
 * EMPLOYEE would rewrite the past every time somebody edited a record, which
 * is why the claim carries its own answer and this only seeds it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('overtime_as_time_off')->default(false)->after('allow_anywhere');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('overtime_as_time_off');
        });
    }
};
