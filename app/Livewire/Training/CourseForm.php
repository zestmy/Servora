<?php

namespace App\Livewire\Training;

use App\Models\Outlet;
use App\Models\Recipe;
use App\Models\TrainingCourse;
use App\Services\Training\QuizGeneratorService;
use App\Services\Training\SourceTextExtractor;
use App\Traits\RequiresActiveCompany;
use App\Traits\ValidatesCompanyOutlet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Author one course, from an SOP, a document, or nothing.
 *
 * The three sources converge on the same editable text box, which is the whole
 * design: an author who imports an SOP and then wants to add a line about this
 * company's own service standard should not have to choose between the import
 * and their own words. What the import gives them is a starting point, not a
 * binding.
 */
class CourseForm extends Component
{
    use RequiresActiveCompany;
    use ValidatesCompanyOutlet;
    use WithFileUploads;

    public ?int $courseId = null;

    public string $title = '';
    public string $summary = '';
    public string $category = '';
    public string $sourceType = 'text';
    public ?int $sourceRecipeId = null;
    public string $content = '';
    public string $videoUrl = '';
    public int $estimatedMinutes = 5;
    public string $status = 'draft';
    public bool $isCompliance = false;
    public ?int $recertifyMonths = null;

    /** Empty means every outlet — see TrainingCourse::scopeVisibleToOutlets. */
    public array $outletIds = [];

    public $upload;
    public $cover;

    /** Set when an import produced nothing readable, so the view can say so. */
    public string $extractionNote = '';

    // Generate-a-quiz panel, shown once the course has material.
    public bool $showGenerate = false;
    public int $questionCount = 8;
    public string $questionDifficulty = 'mixed';

    public function mount(?int $id = null): void
    {
        $this->requireActiveCompany();

        if (! $id) {
            return;
        }

        $course = TrainingCourse::with('outlets:id')->findOrFail($id);

        $this->courseId         = $course->id;
        $this->title            = $course->title;
        $this->summary          = (string) $course->summary;
        $this->category         = (string) $course->category;
        $this->sourceType       = $course->source_type;
        $this->sourceRecipeId   = $course->source_recipe_id;
        $this->content          = (string) $course->content;
        $this->videoUrl         = (string) $course->video_url;
        $this->estimatedMinutes = $course->estimated_minutes;
        $this->status           = $course->status;
        $this->isCompliance     = $course->is_compliance;
        $this->recertifyMonths  = $course->recertify_months;
        $this->outletIds        = $course->outlets->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    protected function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'summary'          => ['nullable', 'string', 'max:1000'],
            'category'         => ['nullable', 'string', 'max:120'],
            'sourceType'       => ['required', Rule::in(array_keys(TrainingCourse::SOURCE_TYPES))],
            'sourceRecipeId'   => ['nullable', 'integer'],
            'content'          => ['nullable', 'string'],
            'videoUrl'         => ['nullable', 'url', 'max:500'],
            'estimatedMinutes' => ['required', 'integer', 'min:1', 'max:600'],
            'status'           => ['required', Rule::in(array_keys(TrainingCourse::STATUSES))],
            'recertifyMonths'  => ['nullable', 'integer', 'min:1', 'max:120'],
            'outletIds'        => ['array'],
            'outletIds.*'      => [$this->outletExistsRule()],
            // 12MB: a photographed policy PDF from a phone is routinely 6-8MB,
            // and the box's post_max_size is what actually caps this.
            'upload'           => ['nullable', 'file', 'mimes:pdf,docx,txt,md', 'max:12288'],
            'cover'            => ['nullable', 'image', 'max:4096'],
        ];
    }

    /**
     * Pull an SOP in as the starting text.
     *
     * Overwrites the content box, and says so in the UI, because the alternative
     * — appending — produces a course with the method in it twice the second
     * time somebody presses the button.
     */
    public function importSop(SourceTextExtractor $extractor): void
    {
        $this->validateOnly('sourceRecipeId');

        if (! $this->sourceRecipeId) {
            $this->addError('sourceRecipeId', 'Choose an SOP to import.');

            return;
        }

        $recipe = Recipe::with(['steps', 'lines.ingredient', 'lines.uom', 'yieldUom'])
            ->findOrFail($this->sourceRecipeId);

        $this->content    = $extractor->fromRecipe($recipe);
        $this->sourceType = 'sop';

        if ($this->title === '') {
            $this->title = $recipe->name;
        }
        if ($this->category === '' && $recipe->category) {
            $this->category = $recipe->category;
        }
        if ($this->videoUrl === '' && $recipe->video_url) {
            $this->videoUrl = $recipe->video_url;
        }

        $this->estimatedMinutes = TrainingCourse::readingMinutes($this->content);
        $this->extractionNote   = '';

        session()->flash('success', "Imported the SOP for \"{$recipe->name}\". Edit it however you like.");
    }

    /**
     * Read an uploaded document into the content box.
     *
     * A document that yields no text is reported plainly rather than as an
     * error — see SourceTextExtractor for why that happens and why it is
     * normal.
     */
    public function importUpload(SourceTextExtractor $extractor): void
    {
        $this->validateOnly('upload');

        if (! $this->upload) {
            $this->addError('upload', 'Choose a file first.');

            return;
        }

        $text = $extractor->fromUpload($this->upload);

        if ($text === '') {
            $this->extractionNote = 'We could not read any text out of that file — it may be a scan or a photo. '
                . 'Paste the wording in below and everything else still works.';

            return;
        }

        $this->content          = $text;
        $this->sourceType       = 'upload';
        $this->estimatedMinutes = TrainingCourse::readingMinutes($text);
        $this->extractionNote   = '';

        if ($this->title === '') {
            $this->title = pathinfo($this->upload->getClientOriginalName(), PATHINFO_FILENAME);
        }

        session()->flash('success', 'Document read. Check the text below before you publish it.');
    }

    public function save(bool $andGenerate = false)
    {
        $this->authorize('training.manage');

        $data = $this->validate();

        $course = $this->courseId
            ? TrainingCourse::findOrFail($this->courseId)
            : new TrainingCourse(['company_id' => Auth::user()->company_id, 'created_by' => Auth::id()]);

        if ($this->cover) {
            $course->cover_path = $this->cover->store('training/covers', 'public');
        }

        if ($this->upload) {
            $course->source_filename = $this->upload->getClientOriginalName();
            $course->source_path     = $this->upload->store('training/sources', 'local');
        }

        $course->fill([
            'title'             => $data['title'],
            'summary'           => $data['summary'] ?: null,
            'category'          => $data['category'] ?: null,
            'source_type'       => $data['sourceType'],
            'source_recipe_id'  => $data['sourceType'] === 'sop' ? $data['sourceRecipeId'] : null,
            'content'           => $data['content'] ?: null,
            'video_url'         => $data['videoUrl'] ?: null,
            'estimated_minutes' => $data['estimatedMinutes'],
            'status'            => $data['status'],
            'is_compliance'     => $this->isCompliance,
            'recertify_months'  => $this->isCompliance ? $data['recertifyMonths'] : null,
        ])->save();

        $course->outlets()->sync(array_map('intval', $this->outletIds));

        $this->courseId = $course->id;
        $this->upload   = null;
        $this->cover    = null;

        if ($andGenerate) {
            return $this->generateQuiz(app(QuizGeneratorService::class));
        }

        session()->flash('success', "\"{$course->title}\" saved.");

        return redirect()->route('training.courses');
    }

    /**
     * Write a quiz for this course.
     *
     * The quiz shell is created before the API call — see
     * QuizGeneratorService::draftQuizFor — so a failed generation leaves
     * something the author can fill in by hand rather than nothing.
     */
    public function generateQuiz(QuizGeneratorService $generator)
    {
        $this->authorize('training.manage');

        if (! $this->courseId) {
            $this->save();

            return null;
        }

        $course = TrainingCourse::findOrFail($this->courseId);

        if (blank($course->content)) {
            session()->flash('error', 'There is no material to write questions from yet.');

            return null;
        }

        $quiz = $course->quizzes()->first() ?? $generator->draftQuizFor($course);

        try {
            $result = $generator->generateForCourse(
                $course,
                $quiz,
                $this->questionCount,
                $this->questionDifficulty,
            );
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return redirect()->route('training.quizzes.edit', $quiz->id);
        }

        $note = $result['dropped'] > 0
            ? " {$result['dropped']} more were discarded as unusable."
            : '';

        session()->flash('success', "Wrote {$result['questions']} questions.{$note} Review them before publishing.");

        return redirect()->route('training.quizzes.edit', $quiz->id);
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        $recipes = Recipe::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('exclude_from_lms', false)
            ->orderBy('name')
            ->get(['id', 'name', 'category']);

        $outlets = Outlet::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $course = $this->courseId ? TrainingCourse::with('quizzes')->find($this->courseId) : null;

        $aiReady = app(QuizGeneratorService::class)->isConfigured();

        return view('livewire.training.course-form', compact('recipes', 'outlets', 'course', 'aiReady'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), [
                'title' => $this->courseId ? 'Edit course' : 'New course',
            ]);
    }
}
