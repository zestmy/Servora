<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An LMS account can belong to an employee, and then needs no password.
 *
 * Staff open the SOP library from the Staff Portal on the PIN (or emailed
 * code) they already clock in with. The account that session maps to is
 * linked here by employee_id, and for somebody who never registered it has no
 * password of its own — the PIN is the credential — and may have no email,
 * since most floor staff sign in by PIN alone.
 *
 * Nullable email is safe against the (company_id, email) unique index: MySQL
 * and SQLite both let any number of NULLs through a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });

        if (! Schema::hasColumn('lms_users', 'employee_id')) {
            Schema::table('lms_users', function (Blueprint $table) {
                $table->foreignId('employee_id')->nullable()->after('outlet_id')
                    ->constrained()->nullOnDelete();
                $table->unique(['company_id', 'employee_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('lms_users', 'employee_id')) {
            Schema::table('lms_users', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'employee_id']);
                $table->dropConstrainedForeignId('employee_id');
            });
        }
    }
};
