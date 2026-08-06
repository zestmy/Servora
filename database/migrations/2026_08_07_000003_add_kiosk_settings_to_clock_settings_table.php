<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The kiosk's own thresholds, which cannot be the ones a phone punch uses.
 *
 * `face_threshold` governs a 1:1 VERIFICATION: the person has already said who
 * they are by picking their name and keying a PIN, and the only question is
 * whether the face agrees. The kiosk asks a harder question — 1:N
 * IDENTIFICATION across everybody enrolled at the outlet — and the error it
 * can make is worse in kind, not just in degree. A 1:1 false accept flags a
 * punch. A 1:N false accept writes the punch onto SOMEBODY ELSE'S attendance
 * record, then leaves the real person unable to clock in, and quietly helps
 * the person who was trying it on.
 *
 * So two numbers instead of one:
 *
 *   kiosk_face_threshold  tighter than the 1:1 line, because the candidate
 *                         pool is the whole outlet rather than one person.
 *
 *   kiosk_face_margin     how far clear of the RUNNER-UP the winner has to
 *                         be. This is the one that actually prevents the bad
 *                         outcome. Two colleagues who both score 0.44 are not
 *                         a match at all, they are a coin toss, and a coin
 *                         toss must never be resolved silently — the kiosk
 *                         falls back to asking rather than guessing.
 *
 * Both switches default ON so a company that registers a kiosk gets a working
 * one, and BYOD stays on so nothing existing changes. What decides policy is
 * the per-outlet punch_mode, not these; these only say what the company is
 * allowed to use at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_settings', function (Blueprint $table) {
            $table->boolean('kiosk_enabled')->default(true)->after('require_face_match');
            $table->boolean('byod_enabled')->default(true)->after('kiosk_enabled');

            $table->decimal('kiosk_face_threshold', 4, 3)->default(0.450)->after('face_threshold');
            $table->decimal('kiosk_face_margin', 4, 3)->default(0.080)->after('kiosk_face_threshold');

            /*
             * How long the kiosk ignores somebody it has just recorded.
             *
             * A hands-free screen in a doorway sees the same person several
             * times in a minute — walking to the fridge, coming back with a
             * tray. Without this, the second sighting is a clock-OUT thirty
             * seconds into the shift. Server-side, because a cooldown the
             * browser keeps is a cooldown a reload clears.
             */
            $table->unsignedSmallInteger('kiosk_cooldown_minutes')->default(3)->after('kiosk_face_margin');
        });
    }

    public function down(): void
    {
        Schema::table('clock_settings', function (Blueprint $table) {
            $table->dropColumn([
                'kiosk_enabled', 'byod_enabled',
                'kiosk_face_threshold', 'kiosk_face_margin', 'kiosk_cooldown_minutes',
            ]);
        });
    }
};
