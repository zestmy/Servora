<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The identity and bank details payroll needs and the system did not hold.
 *
 * A payslip identifies someone by IC, a salary transfer needs a bank and an
 * account number, and every statutory submission — CP39, KWSP, SOCSO — is
 * keyed on the employee's IC plus the EMPLOYER's own reference numbers, which
 * were nowhere on record.
 *
 * All nullable: an incomplete record must not block payroll from running. The
 * exports report what is missing instead, because "which twelve people have no
 * bank account" is a question worth answering directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('ic_number', 20)->nullable()->after('date_of_birth');
            $table->string('bank_name', 60)->nullable()->after('pay_type');
            $table->string('bank_account_no', 40)->nullable()->after('bank_name');
        });

        Schema::table('statutory_settings', function (Blueprint $table) {
            // The employer side of every submission file.
            $table->string('employer_epf_number', 30)->nullable()->after('company_id');
            $table->string('employer_socso_number', 30)->nullable()->after('employer_epf_number');
            // LHDN employer file number, the "E" number on CP39.
            $table->string('employer_tax_number', 30)->nullable()->after('employer_socso_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['ic_number', 'bank_name', 'bank_account_no']);
        });

        Schema::table('statutory_settings', function (Blueprint $table) {
            $table->dropColumn(['employer_epf_number', 'employer_socso_number', 'employer_tax_number']);
        });
    }
};
