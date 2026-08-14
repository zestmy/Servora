<?php

namespace App\Services\Training;

use App\Models\TrainingAnswer;
use App\Models\TrainingAttempt;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuiz;
use App\Models\TrainingSessionPlayer;
use Illuminate\Support\Facades\DB;

/**
 * What an answer is worth.
 *
 * THE SPEED CURVE. A correct answer is worth its full points at zero seconds
 * and HALF at the buzzer, sliding linearly between — the mechanic Kahoot made
 * familiar, and the reason it works is that it never punishes knowing the
 * answer. A player who is sure but slow still banks half; a player who guesses
 * fast still banks nothing. A curve that decayed to zero would reward
 * recklessness, because a fast guess would beat a considered right answer.
 *
 * The floor is applied to the BONUS half only, so the arithmetic stays legible
 * to a player watching the screen: right = at least half, on the buzzer = half,
 * instant = all.
 *
 * STREAKS are additive and capped. Three in a row adds 10% of the base, five
 * adds 20%, and it stops there. Uncapped multiplication is what turns a
 * fifteen-question quiz into "whoever got the first four right has won", which
 * empties the room for the back half — the opposite of what a training session
 * is for.
 */
class ScoringService
{
    /** Fraction of the base a correct answer keeps regardless of speed. */
    public const SPEED_FLOOR = 0.5;

    /** Streak length => bonus as a fraction of base. Highest reached wins. */
    public const STREAK_BONUSES = [3 => 0.10, 5 => 0.20];

    /**
     * Points for one answer.
     *
     * `$streak` is the run length INCLUDING this answer, so the first correct
     * answer arrives as 1.
     */
    public function points(
        TrainingQuestion $question,
        TrainingQuiz $quiz,
        bool $isCorrect,
        float $secondsTaken,
        int $streak = 0,
    ): int {
        if (! $isCorrect) {
            return 0;
        }

        $base    = $question->pointsValue($quiz);
        $allowed = max(1, $question->secondsValue($quiz));

        $ratio = $quiz->speed_bonus
            // Clamped both ends: a clock-skewed negative must not pay more than
            // full, and a late-but-accepted answer must not go below the floor.
            ? self::SPEED_FLOOR + (1 - self::SPEED_FLOOR) * max(0.0, min(1.0, 1 - ($secondsTaken / $allowed)))
            : 1.0;

        $points = (int) round($base * $ratio);

        if ($quiz->streak_bonus) {
            $points += (int) round($base * $this->streakBonus($streak));
        }

        return max(0, $points);
    }

    /** The bonus fraction earned at this streak length. */
    public function streakBonus(int $streak): float
    {
        $bonus = 0.0;

        foreach (self::STREAK_BONUSES as $length => $fraction) {
            if ($streak >= $length) {
                $bonus = $fraction;
            }
        }

        return $bonus;
    }

    /**
     * Record one answer against an attempt and return the row.
     *
     * updateOrCreate, not create: the unique index on (attempt, question) is
     * the real guard, and a player double-tapping an option on a laggy phone
     * must not become a duplicate row or a 500. The SECOND tap simply
     * overwrites the first, which is also what the trainee expects.
     *
     * @param  array<int, int>  $chosen
     */
    public function recordAnswer(
        TrainingAttempt $attempt,
        TrainingQuestion $question,
        TrainingQuiz $quiz,
        array $chosen,
        float $secondsTaken,
        int $streak = 0,
    ): TrainingAnswer {
        $isCorrect = $question->isCorrect($chosen);

        return TrainingAnswer::updateOrCreate(
            [
                'training_attempt_id'  => $attempt->id,
                'training_question_id' => $question->id,
            ],
            [
                'chosen'          => array_values(array_map('intval', $chosen)),
                'is_correct'      => $isCorrect,
                'points_awarded'  => $this->points($question, $quiz, $isCorrect, $secondsTaken, $streak),
                'seconds_taken'   => round($secondsTaken, 2),
                'seconds_allowed' => $question->secondsValue($quiz),
            ],
        );
    }

    /**
     * Roll an attempt's answers up onto the attempt and close it.
     *
     * Totals are summed from the answer rows rather than accumulated as they
     * arrive, so a re-answered question cannot double-count and a resumed
     * attempt cannot lose a question — the rows are the truth.
     */
    public function finalise(TrainingAttempt $attempt, ?TrainingQuiz $quiz = null): TrainingAttempt
    {
        $quiz ??= $attempt->quiz;
        $attempt->loadMissing('answers');

        $questionCount = max($attempt->question_count, $attempt->answers->count());
        $correct       = $attempt->answers->where('is_correct', true)->count();

        // Percent CORRECT, not percent of points — a slow but perfect run must
        // pass. See the migration note.
        $percent = $questionCount > 0 ? round($correct / $questionCount * 100, 2) : 0.0;

        $attempt->forceFill([
            'score'          => (int) $attempt->answers->sum('points_awarded'),
            'max_score'      => $attempt->max_score ?: ($quiz?->maxScore() ?? 0),
            'correct_count'  => $correct,
            'question_count' => $questionCount,
            'percent'        => $percent,
            'passed'         => $percent >= (int) ($quiz->pass_mark ?? 70),
            'completed_at'   => $attempt->completed_at ?? now(),
        ])->save();

        return $attempt;
    }

    /**
     * Push one answer's result onto the live player's running totals.
     *
     * The player row is the scoreboard; the attempt is the record. They are
     * updated together in a transaction because a scoreboard that has moved on
     * while the record has not is the bug that makes a leaderboard argument
     * unresolvable.
     */
    public function applyToPlayer(TrainingSessionPlayer $player, TrainingAnswer $answer): TrainingSessionPlayer
    {
        DB::transaction(function () use ($player, $answer) {
            // Cast before arithmetic. TrainingSessionPlayer mirrors its column
            // defaults for this reason, and the belt-and-braces is cheap: the
            // failure mode was max(null, 0) returning null and writing it into
            // a NOT NULL column.
            $streak = $answer->is_correct ? (int) $player->streak + 1 : 0;

            $player->forceFill([
                'score'          => (int) $player->score + (int) $answer->points_awarded,
                'streak'         => $streak,
                'best_streak'    => max((int) $player->best_streak, $streak),
                'correct_count'  => (int) $player->correct_count + ($answer->is_correct ? 1 : 0),
                'answered_count' => (int) $player->answered_count + 1,
                'last_seen_at'   => now(),
            ])->save();
        });

        return $player;
    }
}
