<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whose account the salary actually lands in.
 *
 * Until now the payment listing sent the EMPLOYEE's name as the account name,
 * on the assumption that the two are the same. Often they are not: someone
 * with no account of their own is paid into a spouse's or a parent's, which is
 * common enough among junior kitchen and service staff to be routine rather
 * than an exception. The bank matches the name against the account and bounces
 * the line when they disagree — a transfer that fails for one person in a
 * batch of fifty, on payday, for a reason nothing in the system could explain.
 *
 * Nullable, and blank is the normal case: an empty column means the account is
 * the employee's own and their own name is what gets sent. Nobody has to fill
 * this in for the ordinary case to keep working exactly as it did.
 *
 * Snapshotted onto payroll_run_lines like every other identity field, for the
 * reason that file's docblock gives: a payslip reprinted next year must say
 * what was true when it was paid, not what the employee record says today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('bank_account_name', 120)->nullable()->after('bank_account_no');
        });

        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->string('bank_account_name', 120)->nullable()->after('bank_account_no');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('bank_account_name');
        });

        Schema::table('payroll_run_lines', function (Blueprint $table) {
            $table->dropColumn('bank_account_name');
        });
    }
};
