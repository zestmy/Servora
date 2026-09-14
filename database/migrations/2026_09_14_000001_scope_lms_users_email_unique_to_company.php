<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each company runs its own training portal, so the same person (same email)
 * must be able to hold an LMS account in more than one company. The original
 * table made email globally unique, which blocked registering with a second
 * company altogether.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->unique(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('lms_users', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'email']);
            $table->unique('email');
        });
    }
};
