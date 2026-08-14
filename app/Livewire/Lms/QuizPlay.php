<?php

namespace App\Livewire\Lms;

use App\Models\TrainingAttempt;
use App\Models\TrainingQuiz;
use App\Services\Training\SelfPacedQuizService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Take a quiz on your own.
 *
 * THE CLOCK IS SERVER-SIDE, and that is the only defensible way to do it. The
 * countdown a trainee watches is a CSS animation, but the seconds that decide
 * their points come from the difference between two server timestamps — when
 * the question was handed over, and when the answer arrived. A client-reported
 * elapsed time is a number the client can choose, and points are on a
 * leaderboard their colleagues can see.
 *
 * `startedAt` is held on the component rather than the database because a
 * per-question timestamp column would be a write per question for something
 * that only has to survive one round trip.
 */
class QuizPlay extends Component
{
    public int $quizId;
    public ?int $attemptId = null;
    public int $index = 0;

    /** Server time the current question was handed over, ISO-8601. */
    public string $startedAt = '';

    /** Option indexes the trainee has tapped, as strings from the checkboxes. */
    public array $chosen = [];

    /** After answering: the result of the question just answered. */
    public bool $showFeedback = false;
    public bool $lastCorrect = false;
    public int $lastPoints = 0;
    public array $lastCorrectIndexes = [];
    public ?string $lastExplanation = null;

    public bool $finished = false;

    public function mount(int $id, SelfPacedQuizService $service): void
    {
        $trainee = Auth::guard('lms')->user();

        $quiz = TrainingQuiz::query()
            ->where('company_id', $trainee->company_id)
            ->published()
            ->findOrFail($id);

        $this->quizId = $quiz->id;

        try {
            $attempt = $service->startOrResume($quiz, $trainee);
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
        $trainee = Auth::guard('lms')->user();

        // Scoped to the trainee, so an attempt id belonging to somebody else
        // is not found rather than played.
        return TrainingAttempt::where('lms_user_id', $trainee->id)
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

        // Server clock, both ends. See the class note.
        $seconds = $this->startedAt
            ? max(0.0, (float) Carbon::parse($this->startedAt)->diffInMilliseconds(now()) / 1000)
            : (float) $question->secondsValue($attempt->quiz);

        // Never pay for time the trainee did not have: a page left open over a
        // break must score as a timeout, not as a negative bonus.
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
            $attempt = $this->attemptId ? $this->attempt()->fresh(['quiz.course', 'answers']) : null;

            return view('livewire.lms.quiz-result', [
                'attempt'     => $attempt,
                'certificate' => $attempt?->certificate()->first(),
            ])->layout('layouts.lms', ['title' => 'Result']);
        }

        $attempt  = $this->attempt();
        $question = $service->questionAt($attempt, $this->index);

        if (! $question) {
            $this->finish($service);

            return view('livewire.lms.quiz-result', [
                'attempt'     => $this->attempt()->fresh(['quiz.course', 'answers']),
                'certificate' => $this->attempt()->certificate()->first(),
            ])->layout('layouts.lms', ['title' => 'Result']);
        }

        $options = $question->optionList();

        // Shuffled per render would reshuffle on every Livewire round trip and
        // move the option under the trainee's thumb. Seeded on the attempt and
        // the question, so it is stable for this person and this question while
        // still differing between people.
        if ($attempt->quiz->shuffle_options) {
            $order = range(0, count($options) - 1);
            mt_srand($attempt->id * 1000 + $question->id);
            shuffle($order);
            mt_srand();
        } else {
            $order = range(0, count($options) - 1);
        }

        return view('livewire.lms.quiz-play', [
            'attempt'  => $attempt,
            'quiz'     => $attempt->quiz,
            'question' => $question,
            'options'  => $options,
            'order'    => $order,
            'total'    => count((array) $attempt->question_order),
            'seconds'  => $question->secondsValue($attempt->quiz),
        ])->layout('layouts.lms', ['title' => $attempt->quiz->title]);
    }
}
