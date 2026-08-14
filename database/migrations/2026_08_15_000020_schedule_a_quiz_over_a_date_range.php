<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A quiz that runs over days instead of over a briefing.
 *
 * The live session is a room: everybody together, the host driving question by
 * question, over in five minutes. That is the wrong shape for a fifteen-branch
 * group across three shifts, where the people you want to reach are never in
 * one place at one time. A SCHEDULED session opens on a date, closes on a date,
 * and staff take it whenever their shift allows; the host does not drive it at
 * all, they just watch the board.
 *
 * IT REUSES training_sessions RATHER THAN GETTING ITS OWN TABLE, because
 * everything downstream already understands a session: attempts hang off one,
 * the standings are grouped by one, the report card counts them. A parallel
 * "challenges" table would need every one of those to learn a second shape, and
 * the first thing to rot would be somebody's score appearing on one board and
 * not the other.
 *
 * THE PIN BECOMES NULLABLE. A room number exists so people in a room can join
 * the right room; a challenge is reached from the staff app by the people it
 * was assigned to, and inventing a PIN nobody types would be a number staff
 * eventually try to use.
 *
 * The window is stored as timestamps rather than dates. "Closes Friday" means
 * the end of Friday to everybody who reads it, and a date column forces every
 * caller to remember that — a closing timestamp says it once.
 */
return new class extends Migration
{
    /*
     * Every step is guarded, and that is not belt-and-braces. MySQL does not
     * roll DDL back, so a migration that fails half-way through leaves the
     * first half applied — and the retry then dies on "column already exists"
     * with no way forward but hand surgery on production. Ask before adding.
     */
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            // 'live' — a host drives a room. 'scheduled' — open for a window.
            if (! Schema::hasColumn('training_sessions', 'mode')) {
                $table->string('mode', 12)->default('live')->after('name');
            }

            if (! Schema::hasColumn('training_sessions', 'opens_at')) {
                $table->timestamp('opens_at')->nullable();
            }

            if (! Schema::hasColumn('training_sessions', 'closes_at')) {
                $table->timestamp('closes_at')->nullable();
            }
        });

        /*
         * The PIN keeps its length and its index — change() rewrites the whole
         * column definition from what is given here, so anything omitted is
         * dropped. The (pin, status) index is a separate object and survives,
         * but the length would silently become 255 if it were left out.
         */
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->string('pin', 8)->nullable()->change();
        });

        if (! Schema::hasIndex('training_sessions', 'training_session_window_idx')) {
            Schema::table('training_sessions', function (Blueprint $table) {
                // "What is open right now for this company" is the query the
                // staff app runs on every visit to the learning screens.
                $table->index(['company_id', 'mode', 'closes_at'], 'training_session_window_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropIndex('training_session_window_idx');
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropColumn(['mode', 'opens_at', 'closes_at']);
        });
    }
};
