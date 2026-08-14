<?php

namespace App\Livewire\Training;

use App\Models\TrainingQuestion;
use App\Models\TrainingQuiz;
use App\Services\Training\QuizGeneratorService;
use App\Traits\RequiresActiveCompany;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Edit a quiz and its questions.
 *
 * The AI writes a first draft; a human owns what ships. That is why the
 * generated questions land in an ordinary editor rather than behind a
 * regenerate-only button — the model is good at turning an SOP into eight
 * plausible questions and bad at knowing that this company calls it a "flat
 * white" and not a "white coffee".
 */
class QuizBuilder extends Component
{
    use RequiresActiveCompany;

    public int $quizId;

    // Quiz settings
    public string $title = '';
    public string $description = '';
    public string $language = 'en';
    /** Empty means everybody — see TrainingQuiz::scopeForSection. */
    public string $sectionId = '';
    public string $status = 'draft';
    public int $passMark = 70;
    public int $defaultSeconds = 20;
    public int $defaultPoints = 1000;
    public bool $speedBonus = true;
    public bool $streakBonus = true;
    /** Share of a question's points a wrong answer costs. 0 = nothing. */
    public int $wrongPenaltyPercent = 0;
    public string $musicUrl = '';
    public bool $shuffleQuestions = true;
    public bool $shuffleOptions = true;
    public int $maxAttempts = 0;
    public bool $issuesCertificate = false;

    // Question editor
    public ?int $editingId = null;
    public string $qType = 'mcq';
    public string $qPrompt = '';
    public array $qOptions = ['', '', '', ''];
    public array $qCorrect = [];
    public string $qExplanation = '';
    public string $qDifficulty = 'medium';
    public string $qTopic = '';
    public ?int $qPoints = null;
    public ?int $qSeconds = null;

    // Regenerate panel
    public bool $showGenerate = false;
    public int $questionCount = 8;
    public string $questionDifficulty = 'mixed';
    public bool $replaceExisting = true;

    public function mount(int $id): void
    {
        $this->requireActiveCompany();

        $quiz = TrainingQuiz::findOrFail($id);

        $this->quizId            = $quiz->id;
        $this->title             = $quiz->title;
        $this->description       = (string) $quiz->description;
        $this->language          = $quiz->language ?: 'en';
        $this->sectionId         = (string) ($quiz->section_id ?? '');
        $this->status            = $quiz->status;
        $this->passMark          = $quiz->pass_mark;
        $this->defaultSeconds    = $quiz->default_seconds;
        $this->defaultPoints     = $quiz->default_points;
        $this->speedBonus        = $quiz->speed_bonus;
        $this->streakBonus       = $quiz->streak_bonus;
        $this->wrongPenaltyPercent = (int) $quiz->wrong_penalty_percent;
        $this->musicUrl          = (string) $quiz->music_url;
        $this->shuffleQuestions  = $quiz->shuffle_questions;
        $this->shuffleOptions    = $quiz->shuffle_options;
        $this->maxAttempts       = $quiz->max_attempts;
        $this->issuesCertificate = $quiz->issues_certificate;
    }

    public function saveSettings(): void
    {
        $this->authorize('training.manage');

        $data = $this->validate([
            'title'          => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'language'       => ['required', Rule::in(array_keys(TrainingQuiz::LANGUAGES))],
            // Scoped to this company: a section id is client-supplied like any
            // other field, and a <select> is not the control.
            'sectionId'      => ['nullable', Rule::exists('sections', 'id')
                ->where('company_id', \Illuminate\Support\Facades\Auth::user()->company_id)],
            'status'         => ['required', Rule::in(array_keys(TrainingQuiz::STATUSES))],
            'passMark'       => ['required', 'integer', 'min:1', 'max:100'],
            'defaultSeconds' => ['required', 'integer', 'min:5', 'max:300'],
            'defaultPoints'  => ['required', 'integer', 'min:100', 'max:5000'],
            'maxAttempts'    => ['required', 'integer', 'min:0', 'max:20'],
            'wrongPenaltyPercent' => ['required', 'integer', 'min:0', 'max:100'],
            'musicUrl'       => ['nullable', 'string', 'max:500', 'url'],
        ]);

        $quiz = TrainingQuiz::withCount('questions')->findOrFail($this->quizId);

        if ($data['status'] === 'published' && $quiz->questions_count === 0) {
            $this->addError('status', 'Add at least one question before publishing.');

            return;
        }

        $quiz->update([
            'title'              => $data['title'],
            'description'        => $data['description'] ?: null,
            'language'           => $data['language'],
            'section_id'         => $this->sectionId ?: null,
            'status'             => $data['status'],
            'pass_mark'          => $data['passMark'],
            'default_seconds'    => $data['defaultSeconds'],
            'default_points'     => $data['defaultPoints'],
            'speed_bonus'        => $this->speedBonus,
            'streak_bonus'       => $this->streakBonus,
            'wrong_penalty_percent' => $data['wrongPenaltyPercent'],
            'music_url'          => $data['musicUrl'] ?: null,
            'shuffle_questions'  => $this->shuffleQuestions,
            'shuffle_options'    => $this->shuffleOptions,
            'max_attempts'       => $data['maxAttempts'],
            'issues_certificate' => $this->issuesCertificate,
        ]);

        session()->flash('success', 'Quiz settings saved.');
    }

    // ── Questions ─────────────────────────────────────────────────────────

    public function newQuestion(): void
    {
        $this->resetQuestionForm();
        $this->editingId = 0; // 0 = a new one is open
    }

    public function editQuestion(int $id): void
    {
        $question = $this->question($id);

        $this->editingId    = $question->id;
        $this->qType        = $question->type;
        $this->qPrompt      = $question->prompt;
        $this->qOptions     = $question->optionList();
        $this->qCorrect     = array_map('strval', (array) $question->correct);
        $this->qExplanation = (string) $question->explanation;
        $this->qDifficulty  = $question->difficulty;
        $this->qTopic       = (string) $question->topic;
        $this->qPoints      = $question->points;
        $this->qSeconds     = $question->seconds;
    }

    public function cancelQuestion(): void
    {
        $this->resetQuestionForm();
    }

    /**
     * Switching type reshapes the options.
     *
     * True/false is the case that matters: leaving four boxes on screen after
     * somebody picks it invites them to type answers that will never be shown.
     */
    public function updatedQType(string $value): void
    {
        if ($value === 'true_false') {
            // In the QUIZ's language, not English. The same wording the
            // generator is told to use, from the same constant — a hand-added
            // question reading "True/False" in an otherwise Malay paper is
            // exactly the inconsistency staff spot and authors do not.
            $this->qOptions = TrainingQuiz::booleanOptionsFor($this->language);
            $this->qCorrect = array_slice($this->qCorrect, 0, 1);
            $this->qCorrect = array_values(array_filter($this->qCorrect, fn ($i) => (int) $i < 2));
        } elseif (count($this->qOptions) < 4) {
            $this->qOptions = array_pad($this->qOptions, 4, '');
        }

        if ($value !== 'multi' && count($this->qCorrect) > 1) {
            $this->qCorrect = array_slice($this->qCorrect, 0, 1);
        }
    }

    public function addOption(): void
    {
        if (count($this->qOptions) < 6) {
            $this->qOptions[] = '';
        }
    }

    public function removeOption(int $index): void
    {
        if (count($this->qOptions) <= 2) {
            return;
        }

        unset($this->qOptions[$index]);
        $this->qOptions = array_values($this->qOptions);

        // Every index above the removed one has shifted down; a correct answer
        // left pointing at the old position would silently mark the wrong
        // option right. This is the same failure the AI parser guards against.
        $this->qCorrect = array_values(array_filter(array_map(
            fn ($i) => (int) $i === $index ? null : ((int) $i > $index ? (string) ((int) $i - 1) : $i),
            $this->qCorrect,
        )));
    }

    public function saveQuestion(): void
    {
        $this->authorize('training.manage');

        $options = array_values(array_filter(array_map('trim', $this->qOptions), fn ($o) => $o !== ''));
        $correct = array_values(array_unique(array_map('intval', $this->qCorrect)));
        sort($correct);

        $this->validate([
            'qPrompt'     => ['required', 'string', 'max:1000'],
            'qType'       => ['required', Rule::in(array_keys(TrainingQuestion::TYPES))],
            'qDifficulty' => ['required', Rule::in(array_keys(TrainingQuestion::DIFFICULTIES))],
            'qTopic'      => ['nullable', 'string', 'max:120'],
            'qPoints'     => ['nullable', 'integer', 'min:100', 'max:5000'],
            'qSeconds'    => ['nullable', 'integer', 'min:5', 'max:300'],
        ]);

        if (count($options) < 2) {
            $this->addError('qOptions', 'A question needs at least two options.');

            return;
        }

        $correct = array_values(array_filter($correct, fn (int $i) => $i < count($options)));

        if ($correct === []) {
            $this->addError('qCorrect', 'Mark which option is correct.');

            return;
        }

        if (count($correct) >= count($options)) {
            $this->addError('qCorrect', 'At least one option has to be wrong, or there is nothing to get wrong.');

            return;
        }

        $quiz = TrainingQuiz::findOrFail($this->quizId);

        $payload = [
            'type'        => count($correct) > 1 ? 'multi' : $this->qType,
            'prompt'      => trim($this->qPrompt),
            'options'     => $options,
            'correct'     => $correct,
            'explanation' => trim($this->qExplanation) ?: null,
            'difficulty'  => $this->qDifficulty,
            'topic'       => trim($this->qTopic) ?: null,
            'points'      => $this->qPoints,
            'seconds'     => $this->qSeconds,
        ];

        if ($this->editingId) {
            $this->question($this->editingId)->update($payload);
        } else {
            $quiz->questions()->create($payload + [
                'sort_order' => (int) $quiz->questions()->max('sort_order') + 1,
            ]);
        }

        $this->resetQuestionForm();

        session()->flash('success', 'Question saved.');
    }

    public function deleteQuestion(int $id): void
    {
        $this->authorize('training.manage');

        $this->question($id)->delete();

        if ($this->editingId === $id) {
            $this->resetQuestionForm();
        }

        session()->flash('success', 'Question deleted.');
    }

    /**
     * Move a question one place.
     *
     * Swaps the two sort_orders rather than renumbering the whole quiz, so a
     * twenty-question quiz is two writes.
     */
    public function move(int $id, string $direction): void
    {
        $this->authorize('training.manage');

        $quiz      = TrainingQuiz::findOrFail($this->quizId);
        $questions = $quiz->questions()->get();
        $position  = $questions->search(fn (TrainingQuestion $q) => $q->id === $id);

        if ($position === false) {
            return;
        }

        $target = $direction === 'up' ? $position - 1 : $position + 1;

        if ($target < 0 || $target >= $questions->count()) {
            return;
        }

        $a = $questions[$position];
        $b = $questions[$target];

        // Sort orders can collide (AI generation writes 0..n, a hand-added one
        // appends), so swap by POSITION rather than trusting the stored values
        // to differ.
        [$aOrder, $bOrder] = [$a->sort_order, $b->sort_order];

        if ($aOrder === $bOrder) {
            $questions->values()->each(fn ($q, $i) => $q->update(['sort_order' => $i]));

            $a = $a->fresh();
            $b = $b->fresh();
            [$aOrder, $bOrder] = [$a->sort_order, $b->sort_order];
        }

        $a->update(['sort_order' => $bOrder]);
        $b->update(['sort_order' => $aOrder]);
    }

    public function regenerate(QuizGeneratorService $generator): void
    {
        $this->authorize('training.manage');

        $quiz   = TrainingQuiz::findOrFail($this->quizId);
        $course = $quiz->course;

        if (! $course) {
            session()->flash('error', 'This quiz is not attached to a course, so there is no material to read.');

            return;
        }

        /*
         * Persist the language BEFORE generating.
         *
         * The generator reads it off the quiz row, and the dropdown in this
         * panel is component state until something saves it — so picking Malay
         * and pressing Rewrite would otherwise return English questions and
         * look like the setting does nothing. Saving it here also means the
         * quiz remembers what it was written in, which the true/false editor
         * then follows.
         */
        if ($quiz->language !== $this->language) {
            $quiz->update(['language' => $this->language]);
            $quiz->refresh();
        }

        try {
            $result = $generator->generateForCourse(
                $course,
                $quiz,
                $this->questionCount,
                $this->questionDifficulty,
                $this->replaceExisting,
            );
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->showGenerate = false;

        $note = $result['dropped'] > 0 ? " {$result['dropped']} were discarded as unusable." : '';

        session()->flash('success', "Wrote {$result['questions']} questions.{$note}");
    }

    /** Scoped by the quiz, so an id from another company's quiz 404s. */
    private function question(int $id): TrainingQuestion
    {
        return TrainingQuestion::where('training_quiz_id', $this->quizId)->findOrFail($id);
    }

    private function resetQuestionForm(): void
    {
        $this->editingId    = null;
        $this->qType        = 'mcq';
        $this->qPrompt      = '';
        $this->qOptions     = ['', '', '', ''];
        $this->qCorrect     = [];
        $this->qExplanation = '';
        $this->qDifficulty  = 'medium';
        $this->qTopic       = '';
        $this->qPoints      = null;
        $this->qSeconds     = null;
        $this->resetErrorBag();
    }

    public function render()
    {
        $quiz      = TrainingQuiz::with('course:id,title', 'section:id,name')->findOrFail($this->quizId);
        $questions = $quiz->questions()->get();
        $aiReady   = app(QuizGeneratorService::class)->isConfigured();

        $sections = \App\Models\Section::active()->ordered()->get(['id', 'name']);

        /*
         * The QR is only drawn for a published quiz, because the link only
         * works for one — a poster printed from a draft would scan to "this
         * link has expired", which reads as a broken system rather than as an
         * unfinished quiz.
         */
        $shareUrl = $quiz->status === 'published' ? $quiz->shareUrl() : null;
        $shareQr  = $shareUrl ? app(\App\Services\Labels\LabelQrService::class)->encode($shareUrl) : null;

        return view('livewire.training.quiz-builder', compact('quiz', 'questions', 'aiReady', 'sections', 'shareUrl', 'shareQr'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => $quiz->title]);
    }
}
