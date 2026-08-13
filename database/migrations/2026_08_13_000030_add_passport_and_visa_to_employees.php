<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passport and visa, for staff who need them.
 *
 * A foreign worker's right to be at work runs out on a date, and the company
 * carries the consequence of missing it. The employee record held an IC number
 * and a nationality and nothing else, so those two dates lived in somebody's
 * spreadsheet or nowhere.
 *
 * Both are plain columns on the employee rather than certifications, because
 * they identify the person rather than record a course they sat. The expiry
 * REMINDERS come free: DocumentExpiry already walks
 * Employee::COMPLIANCE_DOCUMENTS, and these two join that list.
 *
 * Nullable and OPTIONAL on purpose. Most staff are local and have neither, and
 * a document nobody was asked for is not a finding — the same rule that stops
 * a specialist certificate reporting the whole payroll as missing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('passport_number', 50)->nullable()->after('ic_number');
            $table->date('passport_expired_on')->nullable()->after('passport_number');
            $table->string('visa_number', 50)->nullable()->after('passport_expired_on');
            $table->date('visa_expired_on')->nullable()->after('visa_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['passport_number', 'passport_expired_on', 'visa_number', 'visa_expired_on']);
        });
    }
};
