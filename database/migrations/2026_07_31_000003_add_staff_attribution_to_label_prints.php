<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who closed off a label, when that person has no user account.
 *
 * resolved_by points at users, which covers a manager working in the main
 * app. Kitchen staff on the subdomain app authenticate with a PIN and have
 * no user row at all, so without this the "who binned it" column would be
 * blank for precisely the people doing the binning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_prints', function (Blueprint $table) {
            $table->foreignId('resolved_by_employee_id')->nullable()->after('resolved_by')
                ->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('label_prints', function (Blueprint $table) {
            $table->dropForeign(['resolved_by_employee_id']);
            $table->dropColumn('resolved_by_employee_id');
        });
    }
};
