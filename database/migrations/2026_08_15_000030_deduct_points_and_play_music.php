<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A wrong answer can cost you, and the room can have music.
 *
 * WHY A PENALTY IS OPTIONAL AND OFF BY DEFAULT ON EXISTING QUIZZES. Kahoot
 * itself does not deduct — a wrong answer is worth zero — and there is a real
 * reason behind that: this is training, and the person most likely to answer
 * wrongly is the person who most needs to keep playing. A negative score in
 * front of colleagues is how somebody decides training is not for them. But a
 * zero-cost wrong answer also makes guessing free, which is what turns a
 * safety quiz into a lottery when it is the last question of a shift. Both are
 * true, so it is a setting: `wrong_penalty_percent`, as a share of the
 * question's own points, so a question worth double also costs double.
 *
 * THE RUNNING TOTAL IS FLOORED AT ZERO rather than allowed to go negative, and
 * that is not squeamishness. A leaderboard with negative numbers on it stops
 * being a leaderboard and becomes a naming-and-shaming board, which in a
 * kitchen is a thing people remember for years. Individual answers still record
 * their true negative value — the report card needs to know what happened — so
 * the floor lives in the two places a TOTAL is computed, not in the row.
 *
 * points_awarded therefore becomes signed. It is the only column that has to:
 * `score` on the attempt and on the player row are both floored before they are
 * written, so neither can ever hold a negative.
 *
 * MUSIC IS A URL ON THE QUIZ, not a global setting. A three-minute allergen
 * refresher and an hour-long induction want different backing, and the person
 * who knows which is the person writing the quiz. Blank means silence, which is
 * the right default for a device that might be on a service pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_quizzes', function (Blueprint $table) {
            if (! Schema::hasColumn('training_quizzes', 'wrong_penalty_percent')) {
                // 0 = a wrong answer is simply worth nothing, as it was.
                $table->unsignedTinyInteger('wrong_penalty_percent')->default(0)->after('streak_bonus');
            }

            if (! Schema::hasColumn('training_quizzes', 'music_url')) {
                $table->string('music_url', 500)->nullable();
            }
        });

        // Signed, so a penalty can be recorded as what it is.
        Schema::table('training_answers', function (Blueprint $table) {
            $table->integer('points_awarded')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('training_quizzes', function (Blueprint $table) {
            $table->dropColumn(['wrong_penalty_percent', 'music_url']);
        });

        Schema::table('training_answers', function (Blueprint $table) {
            $table->unsignedInteger('points_awarded')->default(0)->change();
        });
    }
};
