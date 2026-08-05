<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a company refuse a punch whose face does not match, rather than
 * recording it for review.
 *
 * `require_face` already exists and does something narrower than its name
 * suggests: it insists a readable face was CAPTURED. A face that is captured
 * and then does not match the enrolled one has always been recorded and
 * flagged — deliberately, on the grounds that a new beard should not cost
 * somebody a day's attendance.
 *
 * That is the right default and it is kept. But it is a policy, not a law,
 * and a company that would rather turn somebody away at the door than let a
 * mismatched face onto the payroll now has a switch for it.
 *
 * Separate from require_face rather than folded into it, because the two are
 * genuinely different demands and a company can reasonably want the first
 * without the second: "always give me a selfie" and "turn away anyone I
 * cannot recognise" are not the same instruction.
 *
 * Defaults FALSE even though require_face defaults true, and deliberately so.
 * This is the only setting here that can send somebody home. Measuring the
 * enrolled faces on production found a capture sitting 0.635 from its nearest
 * sibling — further than the 0.500 line a punch has to clear — which is a
 * genuine person who would be refused by their own enrolment. Enabling that
 * for every company by default, on the strength of one company asking for it,
 * is not a decision a migration should make.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_settings', function (Blueprint $table) {
            $table->boolean('require_face_match')->default(false)->after('require_face');
        });
    }

    public function down(): void
    {
        Schema::table('clock_settings', function (Blueprint $table) {
            $table->dropColumn('require_face_match');
        });
    }
};
