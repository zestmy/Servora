<?php

namespace App\Livewire\Staff\Training;

use App\Livewire\Clock\Staff\StaffComponent;
use App\Models\TrainingAttempt;
use App\Models\TrainingCourse;
use App\Models\TrainingQuiz;
use App\Models\TrainingSession;
use App\Services\Training\SelfPacedQuizService;
use Illuminate\Support\Carbon;

/**
 * Take a quiz on your own, on the staff phone.
 *
 * THE CLOCK IS SERVER-SIDE, and that is the only defensible way to do it. The
 * countdown somebody watches is a CSS animation, but the seconds that decide
 * their points are the difference between two server timestamps — when the
 * question was handed over, and when the answer arrived. A client-reported
 * elapsed time is a number the client can choose, and points are on a
 * leaderboard their colleagues can see.
 */
class QuizPlay extends StaffComponent
{
    public int $quizId;
    public ?int $attemptId = null;
    public int $index = 0;

    /** Server time the current question was handed over, ISO-8601. */
    public string $startedAt = '';

    /** Option indexes tapped, as strings from the buttons. */
    public array $chosen = [];

    /**
     * Where they stand on this quiz, recomputed after every answer.
     *
     * The MOVEMENT is the point rather than the number: a rank that appears at
     * the end is a mark, and a rank that climbs a place between two questions
     * is the reason somebody reads the next one properly. `standingBefore`
     * holds the previous rank so the screen can say which way it went.
     *
     * @var array{rank: int, of: int, ahead: ?string, gap: int}|null
     */
    public ?array $standing = null;

    public ?int $standingBefore = null;

    public bool $showFeedback = false;
    public bool $lastCorrect = false;
    public int $lastPoints = 0;
    public array $lastCorrectIndexes = [];
    public ?string $lastExplanation = null;

    public bool $finished = false;

    /** Set when this run counts towards a scheduled challenge. */
    public ?int $challengeId = null;

    /**
     * Nobody has pressed Start yet.
     *
     * THE START SCREEN EXISTS FOR THREE REASONS and would be worth it for any
     * one of them. It is where the language is chosen, and that choice has to
     * be made before question one rather than discovered at it. It is a beat of
     * "ready?" before a timed thing starts, which is the difference between a
     * game and an ambush — the clock begins the instant a question is on
     * screen. And it is the first TAP INSIDE THIS PAGE, which is the only
     * moment a browser will let the backing music begin: autoplay is refused
     * outside a user gesture, and the tap that navigated here belonged to the
     * previous document.
     */
    public bool $started = false;

    /** The language this run is being read in. Null until Start. */
    public ?string $language = null;

    public function mount(int $id, SelfPacedQuizService $service, ?int $session = null): void
    {
        $employee = $this->staff();

        $quiz = TrainingQuiz::query()
            ->where('company_id', $employee->company_id)
            ->published()
            ->findOrFail($id);

        $this->quizId = $quiz->id;

        // Resolved through the company-scoped model, so a challenge id from
        // another tenant is simply not found rather than played.
        $challenge = $session
            ? TrainingSession::where('company_id', $employee->company_id)->find($session)
            : null;

        $this->challengeId = $challenge?->id;

        // The quiz's own language is the default, and the only option when
        // nothing has been translated.
        $this->language = $quiz->language ?: 'en';
    }

    /**
     * Press Start: open the attempt and put question one on screen.
     *
     * The attempt is created HERE rather than at mount, so opening the page and
     * backing out does not leave an unfinished attempt behind — which on a
     * one-shot challenge would have cost somebody their run for a mis-tap.
     */
    public function begin(SelfPacedQuizService $service): void
    {
        if ($this->started || $this->finished) {
            return;
        }

        $employee = $this->staff();
        $quiz     = $this->quiz();

        // Re-checked against what the quiz actually offers: this arrives from
        // the browser, and an unoffered language would mean a paper half in
        // English for somebody who asked for Malay.
        $offered = $quiz->availableLanguages();

        if (! isset($offered[$this->language])) {
            $this->language = $quiz->language ?: 'en';
        }

        $challenge = $this->challengeId
            ? TrainingSession::where('company_id', $employee->company_id)->find($this->challengeId)
            : null;

        try {
            $attempt = $service->startOrResume($quiz, $employee, $challenge);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->finished = true;

            return;
        }

        /*
         * Written on the attempt for the reporting, not for the scoring — the
         * answer key is the same in every language. "Everybody who read this in
         * English passed and everybody who read it in Malay failed" is a fact
         * about the TRANSLATION, and it is invisible unless it was recorded at
         * the time.
         *
         * A RESUMED attempt keeps the language it was started in: swapping
         * language halfway would leave the answers already given belonging to a
         * different reading of the questions.
         */
        if ($attempt->language) {
            $this->language = $attempt->language;
        } else {
            $attempt->forceFill(['language' => $this->language])->save();
        }

        $this->attemptId = $attempt->id;
        $this->index     = $service->resumeIndex($attempt);
        $this->started   = true;

        if ($this->index >= count((array) $attempt->question_order)) {
            $service->finish($attempt);
            $this->finished = true;

            return;
        }

        $this->startedAt = now()->toIso8601String();
    }

    private function quiz(): TrainingQuiz
    {
        return TrainingQuiz::query()
            ->where('company_id', $this->staff()->company_id)
            ->published()
            ->findOrFail($this->quizId);
    }

    private function attempt(): TrainingAttempt
    {
        // Scoped to the employee, so an attempt id belonging to a colleague is
        // simply not found rather than played.
        return TrainingAttempt::where('employee_id', $this->staff()->id)
            ->with('quiz')
            ->findOrFail($this->attemptId);
    }

    /**
     * Change language part-way through.
     *
     * ALLOWED, and safe, because a translation is only words: the questions,
     * their order and the answer key are identical in every language, so the
     * rest of the run simply renders differently. Somebody who starts in
     * English and finds it heavier going than they expected should not have to
     * abandon the attempt to read it in Malay.
     *
     * THE CLOCK IS NOT RESET. The seconds that decide points are measured from
     * the server stamp taken when the question was handed over, and switching
     * does not touch it — so a language change costs the time it takes, which
     * is the honest price. Resetting would make the switcher a way of buying
     * another twenty seconds on a hard question.
     *
     * Nothing is written to the attempt: the ANSWER rows carry the language now
     * (see the migration), because an attempt read in two languages cannot be
     * described by one column.
     */
    public function switchLanguage(string $code): void
    {
        if ($this->finished) {
            return;
        }

        // Re-checked against what the quiz actually offers — this arrives from
        // the browser, and an unoffered language would leave somebody reading
        // English they did not ask for from here on.
        $offered = $this->quiz()->availableLanguages();

        if (isset($offered[$code])) {
            $this->language = $code;
        }
    }

    /** Tapping an option. Single-answer types replace; multi toggles. */
    public function choose(int $optionIndex, bool $multi): void
    {
        if ($this->showFeedback) {
            return;
        }

        if (! $multi) {
            $this->chosen = [$optionIndex];

            return;
        }

        $key = array_search($optionIndex, array_map('intval', $this->chosen), true);

        if ($key === false) {
            $this->chosen[] = $optionIndex;
        } else {
            unset($this->chosen[$key]);
            $this->chosen = array_values($this->chosen);
        }
    }

    public function submit(SelfPacedQuizService $service): void
    {
        if ($this->showFeedback || $this->finished) {
            return;
        }

        $attempt  = $this->attempt();
        $question = $service->questionAt($attempt, $this->index);

        if (! $question) {
            $this->finish($service);

            return;
        }

        $seconds = $this->startedAt
            ? max(0.0, (float) Carbon::parse($this->startedAt)->diffInMilliseconds(now()) / 1000)
            : (float) $question->secondsValue($attempt->quiz);

        // Never pay for time the person did not have: a page left open over a
        // break scores as a timeout, not as a negative bonus.
        $seconds = min($seconds, (float) $question->secondsValue($attempt->quiz));

        $answer = $service->answer(
            $attempt,
            $question,
            array_map('intval', $this->chosen),
            $seconds,
            $this->language,
        );

        $this->showFeedback       = true;
        $this->lastCorrect        = $answer->is_correct;
        $this->lastPoints         = (int) $answer->points_awarded;
        $this->lastCorrectIndexes = array_map('intval', (array) $question->correct);
        $this->lastExplanation    = $question->explanationIn($this->language);

        /*
         * The sound and the colour, fired from the SERVER's verdict.
         *
         * It would be less code to have the browser compare the tapped index
         * against a correct one it was handed — and it would also mean shipping
         * the answer key to the phone before the question is answered. The
         * round trip is already happening; the event rides back on it.
         */
        /*
         * The board, after this answer landed.
         *
         * Their own total is summed from the ROWS rather than read off the
         * attempt: the attempt's `score` is written by finalise() at the end,
         * so mid-quiz it is still zero. The rows are the truth until then —
         * the same reason finalise sums them rather than accumulating.
         */
        $before = $this->standing['rank'] ?? null;

        $this->standing = app(\App\Services\Training\LeaderboardService::class)->standingOnQuiz(
            $attempt->training_quiz_id,
            $this->staff()->id,
            (int) $attempt->answers()->sum('points_awarded'),
        );

        $this->standingBefore = $before;

        [$right, $wrong] = TrainingQuiz::verdictWordsFor($this->language);

        $this->dispatch(
            'answer-scored',
            correct: $answer->is_correct,
            points: (int) $answer->points_awarded,
            // The word for the ribbon, in whatever they chose to read this in.
            label: $answer->is_correct ? $right : $wrong,
        );
    }

    /** The clock ran out with nothing chosen. */
    public function timeout(SelfPacedQuizService $service): void
    {
        if ($this->showFeedback || $this->finished) {
            return;
        }

        $this->chosen = [];
        $this->submit($service);
    }

    public function nextQuestion(SelfPacedQuizService $service): void
    {
        $attempt = $this->attempt();

        $this->index++;
        $this->chosen       = [];
        $this->showFeedback = false;
        $this->startedAt    = now()->toIso8601String();

        if ($this->index >= count((array) $attempt->question_order)) {
            $this->finish($service);
        }
    }

    private function finish(SelfPacedQuizService $service): void
    {
        $service->finish($this->attempt());
        $this->finished = true;
    }

    /**
     * The result screen's own data.
     *
     * `readableCourse` is the fix for a reported 404. The result offered "Back
     * to the course" whenever the quiz had one — but a quiz can be PUBLISHED
     * while its course is still a draft, which is exactly the state the menu
     * knowledge paper was in, and the staff course screen quite correctly
     * refuses to open a draft. The link was therefore a button that could only
     * ever produce a 404, and the person who found it had just finished a quiz.
     *
     * Resolved through the same query the course screen uses rather than by
     * checking `status` here: outlet visibility can retire a course too, and
     * two rules that must agree are one rule asked twice.
     */
    private function readableCourse(?TrainingAttempt $attempt): ?TrainingCourse
    {
        $courseId = $attempt?->quiz?->training_course_id;

        if (! $courseId) {
            return null;
        }

        $employee = $this->staff();

        return TrainingCourse::query()
            ->where('company_id', $employee->company_id)
            ->published()
            ->visibleToOutlets($employee->trainingOutletIds())
            ->find($courseId);
    }

    public function render(SelfPacedQuizService $service)
    {
        // Before anything else: the start screen. Nothing has been created yet
        // at this point, so leaving is free.
        if (! $this->started && ! $this->finished) {
            $quiz = $this->quiz()->loadCount('questions');

            return view('livewire.staff.training.quiz-start', [
                'quiz'      => $quiz,
                'languages' => $quiz->availableLanguages(),
                'challenge' => $this->challengeId
                    ? TrainingSession::where('company_id', $this->staff()->company_id)->find($this->challengeId)
                    : null,
                'best' => TrainingAttempt::where('employee_id', $this->staff()->id)
                    ->where('training_quiz_id', $quiz->id)
                    ->completed()
                    ->orderByDesc('percent')
                    ->first(),
            ])->layout('layouts.clock-staff', $this->shell($quiz->title));
        }

        if ($this->finished || ! $this->attemptId) {
            $attempt = $this->attemptId
                ? $this->attempt()->fresh(['quiz.course', 'answers'])
                : null;

            return view('livewire.staff.training.quiz-result', [
                'attempt'     => $attempt,
                'certificate' => $attempt?->certificate()->first(),
                'course'      => $this->readableCourse($attempt),
            ])->layout('layouts.clock-staff', $this->shell('Result'));
        }

        $attempt  = $this->attempt();
        $question = $service->questionAt($attempt, $this->index);

        if (! $question) {
            $this->finish($service);

            $done = $this->attempt()->fresh(['quiz.course', 'answers']);

            return view('livewire.staff.training.quiz-result', [
                'attempt'     => $done,
                'certificate' => $done->certificate()->first(),
                'course'      => $this->readableCourse($done),
            ])->layout('layouts.clock-staff', $this->shell('Result'));
        }

        $question->loadMissing('translations');

        $options = $question->optionListIn($this->language);

        // Seeded on the attempt and the question rather than shuffled per
        // render: a fresh shuffle on every Livewire round trip would move the
        // option out from under somebody's thumb.
        $order = range(0, count($options) - 1);

        if ($attempt->quiz->shuffle_options) {
            mt_srand($attempt->id * 1000 + $question->id);
            shuffle($order);
            mt_srand();
        }

        return view('livewire.staff.training.quiz-play', [
            'attempt'  => $attempt,
            'quiz'     => $attempt->quiz,
            'question' => $question,
            'prompt'   => $question->promptIn($this->language),
            'options'  => $options,
            'order'    => $order,
            'total'    => count((array) $attempt->question_order),
            'seconds'  => $question->secondsValue($attempt->quiz),
        ])->layout('layouts.clock-staff', $this->shell($attempt->quiz->title));
    }
}
