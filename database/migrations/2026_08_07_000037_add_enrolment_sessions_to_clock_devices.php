<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A time-boxed window in which a kiosk may enrol faces.
 *
 * Enrolment is the ROOT OF TRUST for everything else the kiosk does. A punch
 * is only as good as the enrolment it matched against, so a tablet that would
 * let anyone walk up and record their own face against a colleague's name
 * makes the margin gate, the thresholds and the review queue all decorative —
 * the fraud would be committed once, in advance, and every punch afterwards
 * would be genuinely, verifiably that face.
 *
 * So it is gated the same way pairing is, and for the same reason: a manager
 * at a desk authorises it, somebody at the counter uses it, and no password is
 * ever typed into a device that lives in a public room.
 *
 *   enrol_code            short code a manager reads out. Consumed on use.
 *   enrol_code_expires_at how long the code is worth redeeming.
 *   enrol_until           how long the window stays open once redeemed. This
 *                         is the one that matters: enrolment mode CLOSES BY
 *                         ITSELF. A mode somebody has to remember to switch
 *                         off is a mode that is still on next Tuesday.
 *   enrol_by             the manager who authorised it, written onto every
 *                         capture taken in the window, because
 *                         employee_face_descriptors.enrolled_by has to name a
 *                         person and a kiosk has nobody signed in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_devices', function (Blueprint $table) {
            $table->string('enrol_code', 12)->nullable()->after('user_agent');
            $table->dateTime('enrol_code_expires_at')->nullable()->after('enrol_code');
            $table->dateTime('enrol_until')->nullable()->after('enrol_code_expires_at');
            $table->foreignId('enrol_by')->nullable()->after('enrol_until')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clock_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enrol_by');
            $table->dropColumn(['enrol_code', 'enrol_code_expires_at', 'enrol_until']);
        });
    }
};
