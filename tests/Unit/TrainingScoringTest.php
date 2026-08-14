<?php

namespace Tests\Unit;

use App\Models\TrainingQuestion;
use App\Models\TrainingQuiz;
use App\Services\Training\ScoringService;
use PHPUnit\Framework\TestCase;

/**
 * The scoring curve, on its own.
 *
 * No database: points are a pure function of the question, the quiz settings,
 * the elapsed seconds and the streak, and the arithmetic is the part that
 * decides whether a leaderboard is worth playing. Unmodelled here on purpose so
 * the curve can be argued about without a migration running.
 */
class TrainingScoringTest extends TestCase
{
    private function quiz(array $overrides = []): TrainingQuiz
    {
        $quiz = new TrainingQuiz();
        $quiz->forceFill(array_merge([
            'default_points' => 1000,
            'default_seconds' => 20,
            'speed_bonus'    => true,
            'streak_bonus'   => true,
            'pass_mark'      => 70,
        ], $overrides));

        return $quiz;
    }

    private function question(array $overrides = []): TrainingQuestion
    {
        $question = new TrainingQuestion();
        $question->forceFill(array_merge([
            'type'    => 'mcq',
            'prompt'  => 'What temperature?',
            'options' => ['4°C', '8°C', '12°C', '18°C'],
            'correct' => [0],
        ], $overrides));

        return $question;
    }

    /**
     * The headline of the whole mechanic: knowing the answer is never worth
     * nothing, however slow you were.
     */
    public function test_a_correct_answer_on_the_buzzer_still_banks_half(): void
    {
        $scoring = new ScoringService();

        $this->assertSame(
            500,
            $scoring->points($this->question(), $this->quiz(), true, 20.0, 0)
        );
    }

    public function test_an_instant_correct_answer_banks_the_lot(): void
    {
        $scoring = new ScoringService();

        $this->assertSame(
            1000,
            $scoring->points($this->question(), $this->quiz(), true, 0.0, 0)
        );
    }

    public function test_the_curve_between_is_linear(): void
    {
        $scoring = new ScoringService();

        // Half the time gone → half the bonus half gone → 75%.
        $this->assertSame(
            750,
            $scoring->points($this->question(), $this->quiz(), true, 10.0, 0)
        );
    }

    /**
     * The grace window means an answer can arrive AFTER the limit. It must not
     * pay less than the floor — being a second late is not worse than not
     * knowing.
     */
    public function test_a_late_but_accepted_answer_does_not_drop_below_the_floor(): void
    {
        $scoring = new ScoringService();

        $this->assertSame(
            500,
            $scoring->points($this->question(), $this->quiz(), true, 22.0, 0)
        );
    }

    public function test_a_wrong_answer_is_worth_nothing_however_fast(): void
    {
        $scoring = new ScoringService();

        $this->assertSame(
            0,
            $scoring->points($this->question(), $this->quiz(), false, 0.1, 4)
        );
    }

    public function test_turning_the_speed_bonus_off_pays_full_points_at_any_speed(): void
    {
        $scoring = new ScoringService();
        $quiz    = $this->quiz(['speed_bonus' => false, 'streak_bonus' => false]);

        $this->assertSame(1000, $scoring->points($this->question(), $quiz, true, 19.9, 0));
    }

    /**
     * Capped, and that is the point — see the class note on ScoringService. An
     * uncapped multiplier ends the game after four questions.
     */
    public function test_the_streak_bonus_steps_and_then_stops(): void
    {
        $scoring = new ScoringService();

        $this->assertSame(0.0,  $scoring->streakBonus(2));
        $this->assertSame(0.10, $scoring->streakBonus(3));
        $this->assertSame(0.10, $scoring->streakBonus(4));
        $this->assertSame(0.20, $scoring->streakBonus(5));
        $this->assertSame(0.20, $scoring->streakBonus(50));
    }

    public function test_the_streak_bonus_is_added_to_the_speed_points(): void
    {
        $scoring = new ScoringService();

        // Instant + a five-run: 1000 + 200.
        $this->assertSame(
            1200,
            $scoring->points($this->question(), $this->quiz(), true, 0.0, 5)
        );
    }

    /** A per-question override beats the quiz default. */
    public function test_a_question_can_be_worth_more_than_the_quiz_default(): void
    {
        $scoring  = new ScoringService();
        $question = $this->question(['points' => 2000, 'seconds' => 10]);

        // Half of the question's OWN ten seconds gone, so three quarters of its
        // own two thousand points — neither number comes from the quiz.
        $this->assertSame(
            1500,
            $scoring->points($question, $this->quiz(['streak_bonus' => false]), true, 5.0, 0)
        );
    }

    // ── Correctness ───────────────────────────────────────────────────────

    public function test_a_multi_select_answer_ignores_the_order_it_was_tapped_in(): void
    {
        $question = $this->question([
            'type'    => 'multi',
            'options' => ['Milk', 'Egg', 'Salt', 'Peanut'],
            'correct' => [0, 1, 3],
        ]);

        $this->assertTrue($question->isCorrect([3, 0, 1]));
        $this->assertTrue($question->isCorrect([0, 1, 3, 3]));
        $this->assertFalse($question->isCorrect([0, 1]));
        $this->assertFalse($question->isCorrect([0, 1, 2, 3]));
    }

    /** Running out of time records an empty choice, which is never correct. */
    public function test_an_empty_answer_is_never_correct(): void
    {
        $this->assertFalse($this->question()->isCorrect([]));
    }
}
