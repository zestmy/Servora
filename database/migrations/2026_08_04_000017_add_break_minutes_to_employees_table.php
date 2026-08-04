<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A personal break allowance, overriding the roster's.
     *
     * NULLABLE is the whole design. Null means "whatever the roster says",
     * which is what it means for almost everybody — an override exists for
     * the exception (a medical need, a negotiated arrangement), and defaulting
     * it to a number would silently pin every employee to a value nobody
     * chose and detach them from the roster the day it changed.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedSmallInteger('break_minutes')->nullable()->after('service_points_entitlement');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('break_minutes');
        });
    }
};
