<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an employee lives, and where to post things if that is somewhere else.
 *
 * The record already held an address for the EMERGENCY CONTACT but none for
 * the employee themselves, so anything that has to be posted — an EA form, a
 * letter of employment, a termination notice — had no address to go to.
 *
 * TWO FIELDS, NOT THREE. `mailing_address` is NULL when post goes to the home
 * address, which is the ordinary case, and holds a different address when it
 * does not. The obvious alternative — a `mailing_same_as_home` boolean beside
 * a `mailing_address` — gives two columns that can contradict each other, and
 * then every reader has to decide which one to believe. Null already means
 * "the same", unambiguously, and Employee::mailingAddress() is the one place
 * that resolves it.
 *
 * TEXT rather than the emergency contact's string(255): a Malaysian address
 * with a unit number, a taman, a postcode and a state runs past 255 characters
 * often enough, and truncating somebody's address silently is how post goes
 * missing. The form keeps them to a sane length; the column does not have to
 * be the thing that enforces it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->text('home_address')->nullable()->after('phone');
            $table->text('mailing_address')->nullable()->after('home_address');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['home_address', 'mailing_address']);
        });
    }
};
