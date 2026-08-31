<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How loudly the clock announces itself, as a company's own choice.
 *
 * The clock makes two kinds of noise and until now neither could be turned
 * off. A synthesised chirp acknowledges something IN FLIGHT — a face found, a
 * frame unreadable — and an mp3 sounds the OUTCOME: a punch recorded, a punch
 * refused. Both were unconditional, which is fine on a phone in somebody's hand
 * and is a decision somebody else has to live with on a tablet bolted to a
 * counter in a room where people are working.
 *
 * Three values, because two would not have covered the actual complaint:
 *
 *   full   both. The default, and what every company has today.
 *   chirp  the short tones only. For a room where the full sound is too much
 *          but silence would leave a face scan with no feedback at all — you
 *          touch nothing, so without a sound there is no confirmation for
 *          somebody looking AT the camera rather than the screen beside it.
 *   off    silent. For a dining room, or a counter within earshot of guests.
 *
 * `chirp` is the value that earns the third option. Reducing this to a boolean
 * would force a room that finds the mp3 intrusive to choose between it and no
 * feedback whatsoever, and the second is what makes people stand at a tablet
 * leaning in, unsure whether it saw them.
 *
 * COMPANY-WIDE, and deliberately not per outlet. The complaint it answers is
 * about the sound itself rather than about one room, and a per-outlet control
 * is a row to maintain for every branch to say the same thing. If one noisy
 * kitchen ever genuinely differs from a quiet dining room, this becomes a
 * nullable override on outlets and this column stays as the default.
 *
 * IT DOES NOT GOVERN THE QUIZ. Training keeps its own per-device toggle: a
 * phone in a trainee's hand and a tablet on a counter are not the same room,
 * and one control over both would mean muting a shop floor to silence a quiz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_settings', function (Blueprint $table) {
            $table->enum('sound_mode', ['off', 'chirp', 'full'])
                ->default('full')
                ->after('kiosk_allow_pin');
        });
    }

    public function down(): void
    {
        Schema::table('clock_settings', function (Blueprint $table) {
            $table->dropColumn('sound_mode');
        });
    }
};
