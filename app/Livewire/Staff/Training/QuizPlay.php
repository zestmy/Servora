<?php

namespace App\Livewire\Staff\Training;

use App\Livewire\Clock\Staff\StaffComponent;
use App\Models\TrainingAttempt;
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

    public bool $showFeedback = false;
    public bool $lastCorrect = false;
    public int $lastPoints = 0;
    public array $lastCorrectIndexes = [];
    public ?string $lastExplanation = null;

    public bool $finished = false;

    /** Set when this run counts towards a scheduled challenge. */
    public ?int $challengeId = null;

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

        try {
            $attempt = $service->startOrResume($quiz, $employee, $challenge);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->finished = true;

            return;
        }

        $this->attemptId = $attempt->id;
        $this->index     = $service->resumeIndex($attempt);

        if ($this->index >= count((array) $attempt->question_order)) {
            $service->finish($attempt);
            $this->finished = true;

            return;
        }

        $this->startedAt = now()->toIso8601String();
    }

    private function attempt(): TrainingAttempt
    {
        // Scoped to the employee, so an attempt id belonging to a colleague is
        // simply not found rather than played.
        return TrainingAttempt::where('employee_id', $this->staff()->id)
            ->with('quiz')
            ->findOrFail($this->attemptId);
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

        $answer = $service->answer($attempt, $question, array_map('intval', $this->chosen), $seconds);

        $this->showFeedback       = true;
        $this->lastCorrect        = $answer->is_correct;
        $this->lastPoints         = $answer->points_awarded;
        $this->lastCorrectIndexes = array_map('intval', (array) $question->correct);
        $this->lastExplanation    = $question->explanation;
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

    public function render(SelfPacedQuizService $service)
    {
        if ($this->finished || ! $this->attemptId) {
            $attempt = $this->attemptId
                ? $this->attempt()->fresh(['quiz.course', 'answers'])
                : null;

            return view('livewire.staff.training.quiz-result', [
                'attempt'     => $attempt,
                'certificate' => $attempt?->certificate()->first(),
            ])->layout('layouts.clock-staff', $this->shell('Result'));
        }

        $attempt  = $this->attempt();
        $question = $service->questionAt($attempt, $this->index);

        if (! $question) {
            $this->finish($service);

            return view('livewire.staff.training.quiz-result', [
                'attempt'     => $this->attempt()->fresh(['quiz.course', 'answers']),
                'certificate' => $this->attempt()->certificate()->first(),
            ])->layout('layouts.clock-staff', $this->shell('Result'));
        }

        $options = $question->optionList();

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
            'options'  => $options,
            'order'    => $order,
            'total'    => count((array) $attempt->question_order),
            'seconds'  => $question->secondsValue($attempt->quiz),
        ])->layout('layouts.clock-staff', $this->shell($attempt->quiz->title));
    }
}
