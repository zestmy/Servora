<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-employee PIN for the staff label app on the company subdomain.
 *
 * HASHED, never stored or displayed in the clear. A PIN is only ever seen
 * once, at the moment a manager generates it.
 *
 * A null PIN means no access — that is the whole access-control mechanism,
 * so no separate "can use labels" flag is needed. Revoking is setting it
 * back to null.
 *
 * label_pin_set_at is what makes a staff session last "until the PIN
 * changes": the session records a fingerprint taken at sign-in, and any
 * change to the PIN moves the fingerprint and invalidates every session
 * that was opened with the old one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('label_pin')->nullable()->after('is_active');
            $table->timestamp('label_pin_set_at')->nullable()->after('label_pin');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['label_pin', 'label_pin_set_at']);
        });
    }
};
