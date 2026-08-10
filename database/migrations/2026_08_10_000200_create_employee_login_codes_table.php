<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time codes emailed to staff who sign in without a PIN.
 *
 * 52 of 53 active employees have an email address on file and only 7 have a
 * PIN, so most of the workforce could not reach the staff app at all. A code
 * sent to an address already on their record proves the same thing a PIN does —
 * that this is them — without a manager having to enrol each person by hand.
 *
 * A row per issue rather than a column on employees, for three reasons: an
 * expired or spent code stays visible for a while, which is what makes "I never
 * got it" answerable; issuing a second code cannot silently overwrite the first
 * one somebody is mid-way through typing; and rate limiting can count real
 * events instead of guessing.
 *
 * The code itself is HASHED. It is a credential for the minutes it lives, and a
 * database that can hand out sign-ins to anyone who reads it is a database that
 * cannot be shared with support.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_login_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('requested_ip', 45)->nullable();
            $table->timestamps();

            // Every lookup is "the live code for this employee, newest first".
            $table->index(['employee_id', 'consumed_at', 'expires_at']);
        });

        Schema::table('employees', function (Blueprint $table) {
            /*
             * The employee's own decision to stop using their PIN.
             *
             * Separate from clearing label_pin, which is the manager's control
             * and how access is granted and revoked in this product. Somebody
             * choosing email must not look, to their manager, like somebody who
             * was never given a PIN — and turning it back on should not need the
             * manager to reissue a credential the employee still knows.
             */
            $table->timestamp('pin_login_disabled_at')->nullable()->after('label_pin_set_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_login_codes');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('pin_login_disabled_at');
        });
    }
};
