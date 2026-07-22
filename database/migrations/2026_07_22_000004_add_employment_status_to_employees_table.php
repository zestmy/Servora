<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // probation | confirmed | extended_probation | outsourcing
            $table->string('employment_status', 30)->nullable()->after('join_date');
            // Probation/extension: until when. Confirmed: since when.
            $table->date('employment_status_date')->nullable()->after('employment_status');
            // Outsourcing only: provider name (e.g. Experiva or a custom name).
            $table->string('outsourcing_company', 100)->nullable()->after('employment_status_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['employment_status', 'employment_status_date', 'outsourcing_company']);
        });
    }
};
