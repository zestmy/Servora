<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the kiosk will accept a PIN when it cannot recognise a face.
 *
 * The fallback is the weakest door in the room and there is no pretending
 * otherwise: a PIN is shareable, which is the whole problem the kiosk exists
 * to solve. A company that has enrolled everybody and wants the face to be the
 * only way in should be able to say so.
 *
 * DEFAULTS ON, and the cost of turning it off is worth stating plainly because
 * it is not the one people expect. The obvious casualty is the new hire nobody
 * has enrolled yet — but that is a small, known group a manager can plan for.
 * The real casualty is the ENROLLED cook at 6am with a hairnet, steamed-up
 * glasses and a wet face, who is on the roster, has three good captures on
 * file, and simply cannot be read this morning. With this off, that person
 * cannot clock in at all and their manager has to mark the grid by hand.
 *
 * So it is a deliberate choice, not a default, and the setting screen says
 * what it costs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_settings', function (Blueprint $table) {
            $table->boolean('kiosk_allow_pin')->default(true)->after('kiosk_cooldown_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('clock_settings', function (Blueprint $table) {
            $table->dropColumn('kiosk_allow_pin');
        });
    }
};
