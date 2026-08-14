<?php

namespace App\Services\Training;

use App\Models\Employee;
use App\Models\TrainingAnswer;
use App\Models\TrainingAttempt;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuiz;
use App\Models\TrainingSession;

/**
 * A quiz taken alone, on a phone, between covers.
 *
 * Same records as a live session — one attempt, one answer row per question —
 * because the report card and the leaderboard must not care how somebody
 * learned the material. What differs is who holds the clock: here the trainee's
 * own screen starts each question, and the elapsed time is measured from the
 * moment the server handed the question over.
 *
 * WHY THE ATTEMPT IS CREATED UP FRONT AND RESUMED. Staff do this on a shift.
 * They will be interrupted, and a quiz that loses six answers because somebody
 * had to take a delivery is a quiz nobody finishes. An unfinished attempt is
 * picked back up where it stopped.
 */
class SelfPacedQuizService
{
    public function __construct(
        private ScoringService $scoring,
        private CertificateService $certificates,
    ) {
    }

    /**
     * This employee's live attempt at this quiz, resumed or freshly started.
     *
     * @throws \RuntimeException when they have used their allowance up
     */
    /**
     * @param  ?TrainingSession  $challenge  a scheduled session this counts towards
     */
    public function startOrResume(
        TrainingQuiz $quiz,
        Employee $employee,
        ?TrainingSession $challenge = null,
    ): TrainingAttempt {
        $quiz->loadMissing('questions');

        if ($quiz->questions->isEmpty()) {
            throw new \RuntimeException('This quiz has no questions yet.');
        }

        if ($challenge && ! $challenge->isOpen()) {
            throw new \RuntimeException('This challenge is not open.');
        }

        /*
         * A challenge attempt is its own thing, and resuming has to match on
         * the session as well as the quiz — otherwise starting a challenge
         * would hand somebody their half-finished practice run, complete with
         * the answers they already gave.
         */
        $open = TrainingAttempt::query()
            ->where('training_quiz_id', $quiz->id)
            ->where('employee_id', $employee->id)
            ->where('mode', $challenge ? 'scheduled' : 'self')
            ->when($challenge, fn ($q) => $q->where('training_session_id', $challenge->id))
            ->when(! $challenge, fn ($q) => $q->whereNull('training_session_id'))
            ->whereNull('completed_at')
            ->latest('id')
            ->first();

        if ($open) {
            return $open;
        }

        /*
         * ONE SHOT AT A CHALLENGE. It is a competition with a leaderboard, and
         * a second run after seeing the answers is not a second attempt, it is
         * a different game. Practice runs at the same quiz stay unlimited —
         * they are a different mode and do not count here.
         */
        if ($challenge) {
            $already = TrainingAttempt::query()
                ->where('training_session_id', $challenge->id)
                ->where('employee_id', $employee->id)
                ->whereNotNull('completed_at')
                ->exists();

            if ($already) {
                throw new \RuntimeException('You have already taken this challenge.');
            }
        }

        $remaining = $challenge ? null : $quiz->attemptsRemaining($employee->id);

        if ($remaining !== null && $remaining <= 0) {
            throw new \RuntimeException('You have used all your attempts at this quiz.');
        }

        $ids = $quiz->questions->pluck('id')->map(fn ($id) => (int) $id);

        if ($quiz->shuffle_questions) {
            $ids = $ids->shuffle();
        }

        return TrainingAttempt::create([
            'company_id'          => $quiz->company_id,
            'training_quiz_id'    => $quiz->id,
            'employee_id'         => $employee->id,
            'outlet_id'           => $employee->outlet_id,
            'training_session_id' => $challenge?->id,
            'mode'                => $challenge ? 'scheduled' : 'self',
            'started_at'       => now(),
            'question_order'   => $ids->values()->all(),
            'question_count'   => $ids->count(),
            'max_score'        => $quiz->maxScore(),
        ]);
    }

    /** The question at this position in the attempt's own order. */
    public function questionAt(TrainingAttempt $attempt, int $index): ?TrainingQuestion
    {
        $ids = array_map('intval', (array) $attempt->question_order);
        $id  = $ids[$index] ?? null;

        return $id ? TrainingQuestion::find($id) : null;
    }

    /**
     * How far in the trainee is — the first question they have not answered.
     *
     * Derived from the answer rows rather than stored, so resuming can never
     * disagree with what was actually recorded.
     */
    public function resumeIndex(TrainingAttempt $attempt): int
    {
        $ids      = array_map('intval', (array) $attempt->question_order);
        $answered = $attempt->answers()->pluck('training_question_id')
            ->map(fn ($id) => (int) $id)->all();

        foreach ($ids as $i => $id) {
            if (! in_array($id, $answered, true)) {
                return $i;
            }
        }

        return count($ids);
    }

    /**
     * Record an answer.
     *
     * The streak is derived from the rows behind this one rather than carried
     * in the session, so a resumed attempt continues the run it was on instead
     * of starting again from zero.
     *
     * @param  array<int, int>  $chosen
     */
    public function answer(
        TrainingAttempt $attempt,
        TrainingQuestion $question,
        array $chosen,
        float $secondsTaken,
    ): TrainingAnswer {
        return $this->scoring->recordAnswer(
            $attempt,
            $question,
            $attempt->quiz,
            $chosen,
            $secondsTaken,
            $question->isCorrect($chosen) ? $this->currentStreak($attempt) + 1 : 0,
        );
    }

    /** Correct answers in an unbroken run ending at the last one recorded. */
    public function currentStreak(TrainingAttempt $attempt): int
    {
        $streak = 0;

        foreach ($attempt->answers()->orderByDesc('id')->pluck('is_correct') as $correct) {
            if (! $correct) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    /** Close the attempt, score it, and issue a certificate if it earned one. */
    public function finish(TrainingAttempt $attempt): TrainingAttempt
    {
        $attempt = $this->scoring->finalise($attempt->fresh('answers'), $attempt->quiz);

        $this->certificates->issueFor($attempt->fresh(['quiz.course', 'employee']));

        return $attempt->fresh();
    }
}
