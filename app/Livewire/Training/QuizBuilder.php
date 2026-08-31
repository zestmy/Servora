<?php

namespace App\Livewire\Training;

use App\Models\TrainingQuestion;
use App\Models\TrainingMusicTrack;
use App\Models\TrainingQuiz;
use App\Services\Training\QuizGeneratorService;
use App\Traits\RequiresActiveCompany;
use Illuminate\Validation\Rule;
use Livewire\Component;
use App\Traits\RejectsUnpreviewableUploads;
use Livewire\WithFileUploads;

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
    use RejectsUnpreviewableUploads;
    use WithFileUploads;

    public int $quizId;

    // Quiz settings
    public string $title = '';
    public string $description = '';
    public string $language = 'en';
    /**
     * Section ids this quiz is for. Empty means everybody.
     *
     * A list rather than one id, because the real audiences are not single
     * sections: a menu paper is for FOH and Bar but not the kitchen. With one
     * id those get written as two quizzes, and two papers drift.
     *
     * @var array<int, string>
     */
    public array $sectionIds = [];

    /** Outlet ids this quiz is for. Empty means all of them. @var array<int, string> */
    public array $outletIds = [];
    public string $status = 'draft';
    public int $passMark = 70;
    public int $defaultSeconds = 20;
    public int $defaultPoints = 1000;
    public bool $speedBonus = true;
    public bool $streakBonus = true;
    /** Share of a question's points a wrong answer costs. 0 = nothing. */
    public int $wrongPenaltyPercent = 0;
    /**
     * Seconds the answer screen stays up before turning its own page.
     * 0 — the default — keeps the tap: the trainee moves on when they have
     * read the explanation.
     */
    public int $autoAdvanceSeconds = 0;
    public bool $shuffleQuestions = true;
    public bool $shuffleOptions = true;
    public int $maxAttempts = 0;
    public bool $issuesCertificate = false;

    // Question editor
    public ?int $editingId = null;

    /**
     * Which language the editor is editing.
     *
     * Empty means the quiz's own — the original, where the type, the points and
     * above all the ANSWER KEY live. A language code means the translation, and
     * only the words are editable there: prompt, options and explanation. A
     * translation that could change which option is correct would be a second
     * answer key, and the first thing to go wrong would be a Malay reader being
     * marked down for the right answer.
     *
     * Machine translation is the reason this exists at all. An unreviewed
     * Malay rendering of a safety question is exactly the thing somebody has to
     * be able to correct by hand, and until now the only remedy was to
     * translate the whole paper again and hope.
     */
    public string $editingLanguage = '';
    public string $qType = 'mcq';
    public string $qPrompt = '';
    public array $qOptions = ['', '', '', ''];
    public array $qCorrect = [];
    public string $qExplanation = '';
    public string $qDifficulty = 'medium';
    public string $qTopic = '';
    public ?int $qPoints = null;
    public ?int $qSeconds = null;

    /**
     * The question's picture — "which of these boards is safe for chicken"
     * is a photograph, not a sentence. One image per question, shared by
     * every translation: the plate looks the same in Malay.
     */
    public $qImage;                    // a fresh upload, not yet saved
    public ?string $qImagePath = null; // what is stored on the question row

    // Regenerate panel
    public bool $showGenerate = false;
    public int $questionCount = 8;
    public string $questionDifficulty = 'mixed';
    public bool $replaceExisting = true;

    /**
     * The language to translate INTO, on the languages card.
     *
     * There is no "generate in another language" any more, and that is the
     * point. Two independent generations produce two different papers, so a
     * Malay reader and an English reader would sit different exams and their
     * scores would not be comparable — on a leaderboard they share. One set of
     * questions, translated, is the only version of this that means anything.
     */
    public string $translateTo = '';

    /**
     * An uploaded backing track, and the one already on the quiz.
     *
     * A FILE, not only a YouTube link, because the link cannot autoplay on an
     * iPhone and never will: a user gesture belongs to the frame it happened
     * in, and playVideo() crosses into a cross-origin iframe. A native <audio>
     * element sits in the same document as the Start button, so the tap
     * authorises it directly. See the migration.
     */
    public $musicFile;

    public ?string $musicPath = null;

    /**
     * A track picked from the company's library, as an id, or '' for none.
     *
     * Separate from $musicPath rather than binding the select straight to it.
     * The path is what gets SAVED and can come from three places — the library,
     * a fresh upload, or the quiz's existing value — and a select bound to it
     * would show "no track" for any quiz whose file was uploaded before the
     * library existed and is not in it, which is every one of them until the
     * migration adopts them.
     */
    public string $musicTrackId = '';

    /** Rename the track that is on this quiz, in the library. */
    public string $musicTitle = '';

    /**
     * The upload's own name and size, read BEFORE it is stored.
     *
     * Not Livewire state — they live for the length of one save. store() moves
     * the temporary file, so asking a TemporaryUploadedFile for its size
     * afterwards is asking about something that may no longer be there, and a
     * library row would end up with a null size for every uploaded track while
     * looking like it worked.
     */
    private ?string $pendingOriginalName = null;

    private ?int $pendingSize = null;

    public function mount(int $id): void
    {
        $this->requireActiveCompany();

        $quiz = TrainingQuiz::with('sections:id', 'outlets:id')->findOrFail($id);

        $this->quizId            = $quiz->id;
        $this->title             = $quiz->title;
        $this->description       = (string) $quiz->description;
        $this->language          = $quiz->language ?: 'en';
        $this->sectionIds        = $quiz->sections->pluck('id')->map('strval')->all();
        $this->outletIds         = $quiz->outlets->pluck('id')->map('strval')->all();
        $this->status            = $quiz->status;
        $this->passMark          = $quiz->pass_mark;
        $this->defaultSeconds    = $quiz->default_seconds;
        $this->defaultPoints     = $quiz->default_points;
        $this->speedBonus        = $quiz->speed_bonus;
        $this->streakBonus       = $quiz->streak_bonus;
        $this->wrongPenaltyPercent = (int) $quiz->wrong_penalty_percent;
        $this->autoAdvanceSeconds = (int) $quiz->auto_advance_seconds;
        $this->musicPath         = $quiz->music_path;
        $this->musicTrackId      = (string) (TrainingMusicTrack::where('path', $quiz->music_path)->value('id') ?: '');
        $this->musicTitle        = (string) TrainingMusicTrack::where('path', $quiz->music_path)->value('title');
        $this->shuffleQuestions  = $quiz->shuffle_questions;
        $this->shuffleOptions    = $quiz->shuffle_options;
        $this->maxAttempts       = $quiz->max_attempts;
        $this->issuesCertificate = $quiz->issues_certificate;
    }

    /**
     * Write every question again in another language.
     *
     * The translation lives on the QUESTION, so this quiz gains a language
     * rather than gaining a twin: one attempt, one leaderboard line, one
     * assignment to clear, whichever language somebody read. See the migration
     * on training_question_translations for why the alternative was worse.
     */
    public function translate(QuizGeneratorService $generator): void
    {
        $this->authorize('training.manage');

        $quiz = TrainingQuiz::findOrFail($this->quizId);

        try {
            $result = $generator->translateQuiz($quiz, $this->translateTo);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $label = TrainingQuiz::LANGUAGES[$result['language']] ?? $result['language'];

        // The skipped count is surfaced rather than swallowed: a language is
        // only offered to staff once EVERY question has it, so "9 of 10" is the
        // difference between a language that works and one that silently does
        // not appear.
        $note = $result['skipped'] > 0
            ? " {$result['skipped']} could not be translated safely and were left in "
                . ($quiz->languageLabel()) . ' — translate again to finish them.'
            : '';

        session()->flash('success', "Translated {$result['translated']} questions into {$label}.{$note}");

        $this->translateTo = '';
    }

    /** Drop a language from the quiz entirely. */
    public function removeLanguage(string $language): void
    {
        $this->authorize('training.manage');

        \App\Models\TrainingQuestionTranslation::whereIn(
            'training_question_id',
            TrainingQuiz::findOrFail($this->quizId)->questions()->select('id'),
        )->where('language', $language)->delete();

        session()->flash('success', 'Language removed. Staff are no longer offered it.');
    }

    public function saveSettings(): void
    {
        $this->authorize('training.manage');

        $data = $this->validate([
            'title'          => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'language'       => ['required', Rule::in(array_keys(TrainingQuiz::LANGUAGES))],
            // Scoped to this company: these are client-supplied like any other
            // field, and a set of checkboxes is not the control.
            'sectionIds'   => ['array'],
            'sectionIds.*' => [Rule::exists('sections', 'id')
                ->where('company_id', \Illuminate\Support\Facades\Auth::user()->company_id)],
            'outletIds'    => ['array'],
            'outletIds.*'  => [Rule::exists('outlets', 'id')
                ->where('company_id', \Illuminate\Support\Facades\Auth::user()->company_id)],
            // Scoped to this company for the same reason the sections are:
            // an id from another tenant's library must resolve to nothing
            // rather than to their file.
            'musicTrackId'   => ['nullable', 'string', Rule::exists('training_music_tracks', 'id')
                ->where('company_id', \Illuminate\Support\Facades\Auth::user()->company_id)],
            'musicTitle'     => ['nullable', 'string', 'max:120'],
            'status'         => ['required', Rule::in(array_keys(TrainingQuiz::STATUSES))],
            'passMark'       => ['required', 'integer', 'min:1', 'max:100'],
            'defaultSeconds' => ['required', 'integer', 'min:5', 'max:300'],
            'defaultPoints'  => ['required', 'integer', 'min:100', 'max:5000'],
            'maxAttempts'    => ['required', 'integer', 'min:0', 'max:20'],
            'wrongPenaltyPercent' => ['required', 'integer', 'min:0', 'max:100'],
            'autoAdvanceSeconds' => ['required', 'integer', 'min:0', 'max:60'],
            // 12 MB covers a five-minute track at a sensible bitrate. The box
            // has 1.97 GB of RAM and a modest disk — see the capacity note —
            // so this is a ceiling rather than a formality.
            'musicFile'      => ['nullable', 'file', 'mimetypes:audio/mpeg,audio/mp4,audio/x-m4a,audio/aac,audio/ogg,audio/wav', 'max:12288'],
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
            'status'             => $data['status'],
            'pass_mark'          => $data['passMark'],
            'default_seconds'    => $data['defaultSeconds'],
            'default_points'     => $data['defaultPoints'],
            'speed_bonus'        => $this->speedBonus,
            'streak_bonus'       => $this->streakBonus,
            'wrong_penalty_percent' => $data['wrongPenaltyPercent'],
            /*
             * music_url is deliberately NOT written any more. The field left
             * the form — a YouTube link cannot be background music on a phone,
             * tested both ways on real hardware — but the COLUMN keeps its
             * value untouched, because a direct audio URL saved there still
             * plays through musicFileUrl() and destroying it on the next
             * unrelated settings save would be a quiet data loss.
             */
            'auto_advance_seconds' => $data['autoAdvanceSeconds'],
            'music_path'         => $musicPath = $this->resolveMusicPath(),
            'shuffle_questions'  => $this->shuffleQuestions,
            'shuffle_options'    => $this->shuffleOptions,
            'max_attempts'       => $data['maxAttempts'],
            'issues_certificate' => $this->issuesCertificate,
        ]);

        /*
         * Empty means everybody, on both sides — so detaching everything is a
         * meaningful state and sync() with an empty array is the right call,
         * not a no-op to guard against.
         */
        $quiz->sections()->sync(array_map('intval', $data['sectionIds'] ?? []));
        $quiz->outlets()->sync(array_map('intval', $data['outletIds'] ?? []));

        /*
         * The library entry is written AFTER the quiz, so a save that fails
         * validation cannot leave a track listed that no paper points at.
         */
        if ($musicPath) {
            $track = TrainingMusicTrack::adopt(
                (int) $quiz->company_id,
                $musicPath,
                $this->musicTitle !== '' ? $this->musicTitle : ($this->pendingOriginalName ?: $quiz->title),
                $this->pendingOriginalName,
                $this->pendingSize,
                \Illuminate\Support\Facades\Auth::id(),
            );

            // A rename typed against a track already in the library.
            if ($this->musicTitle !== '' && $track->title !== $this->musicTitle) {
                $track->update(['title' => mb_substr($this->musicTitle, 0, 120)]);
            }

            $this->musicTrackId = (string) $track->id;
            $this->musicTitle   = $track->title;
        } else {
            $this->musicTrackId = '';
            $this->musicTitle   = '';
        }

        $this->musicFile           = null;
        $this->pendingOriginalName = null;
        $this->pendingSize         = null;
        $this->musicPath           = $quiz->fresh()->music_path;

        session()->flash('success', 'Quiz settings saved.');
    }

    /**
     * Take the track off.
     *
     * The stored file is left on disk until Save, for the same reason the
     * course cover is: a quiz edited and abandoned must come back with its
     * music rather than having lost it to a click.
     */
    public function removeMusicFile(): void
    {
        $this->musicFile    = null;
        $this->musicPath    = null;
        $this->musicTrackId = '';
        $this->musicTitle   = '';
    }

    /**
     * Which file this quiz will play, out of the three places one can come from.
     *
     * A FRESH UPLOAD WINS. Somebody who has just chosen a file expects it to be
     * the one that plays, and a library selection left over from before they
     * browsed would silently beat it.
     *
     * Nothing here deletes anything. A track dropped from this quiz stays on
     * disk and stays in the library, because other papers may be playing it —
     * which is the entire reason the library exists.
     */
    private function resolveMusicPath(): ?string
    {
        if ($this->musicFile) {
            // Read first, store second. See the properties' docblock.
            $this->pendingOriginalName = $this->musicFile->getClientOriginalName();
            $this->pendingSize         = (int) $this->musicFile->getSize();

            return $this->musicFile->store('training/music', 'public');
        }

        if ($this->musicTrackId !== '') {
            // Company-scoped by the model's global scope, so an id from another
            // tenant's library resolves to nothing rather than to their file.
            $path = TrainingMusicTrack::whereKey((int) $this->musicTrackId)->value('path');

            if ($path) {
                return $path;
            }
        }

        return $this->musicPath;
    }

    /**
     * Choosing a track from the library.
     *
     * Clears any pending upload. The two are alternatives, and leaving a
     * half-uploaded file in place while the screen shows a chosen track is how
     * somebody saves the wrong song.
     */
    public function updatedMusicTrackId(): void
    {
        if ($this->musicTrackId === '') {
            $this->musicPath  = null;
            $this->musicTitle = '';

            return;
        }

        $track = TrainingMusicTrack::find((int) $this->musicTrackId);

        if (! $track) {
            return;
        }

        $this->musicFile  = null;
        $this->musicPath  = $track->path;
        $this->musicTitle = $track->title;
    }

    /** A new upload supersedes a library choice — see resolveMusicPath(). */
    public function updatedMusicFile(): void
    {
        $this->musicTrackId = '';
    }

    // ── Questions ─────────────────────────────────────────────────────────

    public function newQuestion(): void
    {
        $this->resetQuestionForm();
        $this->editingId = 0; // 0 = a new one is open
    }

    /**
     * Same HEIC trap as the employee photo, same cure: the preview calls
     * temporaryUrl(), which throws for an unconvertible iPhone original and
     * poisons the whole component. See EmployeeForm::updatedPhoto.
     */
    public function updatedQImage(): void
    {
        if (! is_object($this->qImage)) {
            return;
        }

        try {
            $this->qImage = $this->keepPreviewableUpload($this->qImage, 'qImage');
        } catch (\Throwable $e) {
            report($e);

            $this->qImage = null;
            $this->addError('qImage', 'That image could not be read. iPhone tip: Settings → Camera → Formats → Most Compatible, or share it as JPEG.');
        }
    }

    /**
     * Take the picture off. Cleared on screen only — the stored file
     * survives until Save, like the course cover and the music track, so a
     * question edited and abandoned comes back with its image.
     */
    public function removeQuestionImage(): void
    {
        $this->qImage     = null;
        $this->qImagePath = null;
    }

    public function editQuestion(int $id): void
    {
        $this->editingLanguage = '';

        $this->loadQuestion($this->question($id));
    }

    /** Switch the editor between the original and a translation. */
    public function updatedEditingLanguage(): void
    {
        if ($this->editingId) {
            $this->loadQuestion($this->question($this->editingId));
        }
    }

    private function loadQuestion(TrainingQuestion $question): void
    {
        $this->editingId   = $question->id;
        $this->qType       = $question->type;
        $this->qCorrect    = array_map('strval', (array) $question->correct);
        $this->qDifficulty = $question->difficulty;
        $this->qTopic      = (string) $question->topic;
        $this->qPoints     = $question->points;
        $this->qSeconds    = $question->seconds;
        $this->qImage      = null;
        $this->qImagePath  = $question->image_path;

        $translation = $this->editingLanguage
            ? $question->translations()->where('language', $this->editingLanguage)->first()
            : null;

        /*
         * A language with no row yet is seeded from the ORIGINAL rather than
         * left blank. Somebody adding Malay to one question by hand is
         * translating a sentence, not composing one, and an empty box makes
         * them copy the English across first.
         */
        $this->qPrompt      = $translation?->prompt ?: $question->prompt;
        $this->qOptions     = $translation && count($translation->optionList()) === count($question->optionList())
            ? $translation->optionList()
            : $question->optionList();
        $this->qExplanation = (string) ($translation?->explanation ?? $question->explanation);
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
            'qImage'      => ['nullable', 'image', 'max:4096'],
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

        /*
         * EDITING A TRANSLATION TOUCHES THE WORDS AND NOTHING ELSE. The option
         * COUNT is checked against the original because the answer key is a
         * list of indexes into it — a translation with a different number of
         * options does not read a little worse, it marks the wrong answer
         * correct for everybody reading that language. The AI writer refuses
         * the same mismatch for the same reason.
         */
        if ($this->editingId && $this->editingLanguage) {
            $question = $this->question($this->editingId);

            if (count($options) !== count($question->optionList())) {
                $this->addError('qOptions', 'A translation must keep the same number of options as the original — '
                    . count($question->optionList()) . ' here. The answer key is stored as positions in that list.');

                return;
            }

            \App\Models\TrainingQuestionTranslation::updateOrCreate(
                ['training_question_id' => $question->id, 'language' => $this->editingLanguage],
                [
                    'prompt'             => trim($this->qPrompt),
                    'options'            => $options,
                    'explanation'        => trim($this->qExplanation) ?: null,
                    // Reviewed by a person now, whatever wrote it first.
                    'machine_translated' => false,
                ],
            );

            $this->resetQuestionForm();

            session()->flash('success', (TrainingQuiz::LANGUAGES[$this->editingLanguage] ?? 'The translation')
                . ' wording saved.');

            return;
        }

        /*
         * The image is resolved before the payload: a fresh upload wins, an
         * untouched edit keeps what was there, and a removal (qImagePath
         * cleared) writes null. The OLD file is deleted only now, at Save —
         * the same update-leak rule as the company logo, deferred the same
         * way the music track defers it.
         */
        $imagePath = $this->qImage
            ? $this->qImage->store('training/questions', 'public')
            : $this->qImagePath;

        $previousImage = $this->editingId
            ? $this->question($this->editingId)->image_path
            : null;

        if (filled($previousImage) && $previousImage !== $imagePath) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($previousImage);
        }

        $payload = [
            'type'        => count($correct) > 1 ? 'multi' : $this->qType,
            'image_path'  => $imagePath,
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

        $question = $this->question($id);

        // The picture goes with its question, or it leaks on the public disk
        // forever — the same update-leak family as the company logo.
        if ($question->image_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($question->image_path);
        }

        $question->delete();

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

        $languagesBefore = array_values(array_intersect_key(
            TrainingQuiz::LANGUAGES,
            $quiz->translationCoverage(),
        ));

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

        /*
         * Replacing the questions orphans every translation of them — the rows
         * are gone with their questions, by cascade — so the languages card
         * empties and staff stop being offered anything but the original. That
         * is the correct outcome and a surprising one, so it is said out loud
         * rather than discovered when somebody asks where Malay went.
         */
        $lost = $this->replaceExisting && $languagesBefore !== []
            ? ' The ' . implode(' and ', $languagesBefore) . ' translation'
                . (count($languagesBefore) > 1 ? 's are' : ' is')
                . ' gone with the old questions — translate again.'
            : '';

        session()->flash('success', "Wrote {$result['questions']} questions.{$note}{$lost}");
    }

    /** Scoped by the quiz, so an id from another company's quiz 404s. */
    private function question(int $id): TrainingQuestion
    {
        return TrainingQuestion::where('training_quiz_id', $this->quizId)->findOrFail($id);
    }

    private function resetQuestionForm(): void
    {
        $this->editingId       = null;
        $this->editingLanguage = '';
        $this->qType        = 'mcq';
        $this->qPrompt      = '';
        $this->qOptions     = ['', '', '', ''];
        $this->qCorrect     = [];
        $this->qExplanation = '';
        $this->qDifficulty  = 'medium';
        $this->qTopic       = '';
        $this->qPoints      = null;
        $this->qSeconds     = null;
        $this->qImage       = null;
        $this->qImagePath   = null;
        $this->resetErrorBag();
    }

    public function render()
    {
        $quiz      = TrainingQuiz::with('course:id,title', 'sections:id,name', 'outlets:id,name')
            ->findOrFail($this->quizId);
        $questions = $quiz->questions()->get();
        $aiReady   = app(QuizGeneratorService::class)->isConfigured();

        $sections = \App\Models\Section::active()->ordered()->get(['id', 'name']);

        // The library. Company-scoped by the model, so this is only ever this
        // tenant's own tracks.
        $musicTracks = TrainingMusicTrack::ordered()->get();

        $outlets = \App\Models\Outlet::where('company_id', \Illuminate\Support\Facades\Auth::user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        /*
         * The QR is only drawn for a published quiz, because the link only
         * works for one — a poster printed from a draft would scan to "this
         * link has expired", which reads as a broken system rather than as an
         * unfinished quiz.
         */
        $shareUrl = $quiz->status === 'published' ? $quiz->shareUrl() : null;
        $shareQr  = $shareUrl ? app(\App\Services\Labels\LabelQrService::class)->encode($shareUrl) : null;

        $coverage  = $quiz->translationCoverage();
        $available = $quiz->availableLanguages();

        return view('livewire.training.quiz-builder', compact(
            'quiz', 'questions', 'aiReady', 'sections', 'outlets', 'shareUrl', 'shareQr', 'coverage', 'available',
            'musicTracks',
        ))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => $quiz->title]);
    }
}
