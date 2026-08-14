<?php

namespace App\Livewire\Training;

use App\Models\Employee;
use App\Models\Outlet;
use App\Models\TrainingAssignment;
use App\Models\TrainingCourse;
use App\Models\TrainingPath;
use App\Models\TrainingQuiz;
use App\Traits\RequiresActiveCompany;
use App\Traits\ValidatesCompanyOutlet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Who has to complete what, by when.
 *
 * The audience is an outlet or a named person, and the outlet form is the one
 * to reach for — see the migration note. The screen puts it first for that
 * reason rather than alphabetically. An outlet can be NARROWED BY SECTION,
 * because "the kitchen at Bangsar" is a real requirement and neither field
 * expresses it alone.
 *
 * The target can be a course, a learning path, or ONE QUIZ. The last exists
 * because a course now carries several — a kitchen paper beside a floor one, an
 * English set beside a Malay one — so "do the lamb rack course" stopped saying
 * which paper somebody actually has to sit.
 */
class Assignments extends Component
{
    use RequiresActiveCompany;
    use ValidatesCompanyOutlet;
    use WithPagination;

    public bool $showModal = false;

    public string $targetType = 'course';   // course | path | quiz
    public ?int $courseId = null;
    public ?int $pathId = null;
    public ?int $quizId = null;

    public string $audienceType = 'outlet'; // outlet | trainee
    public ?int $outletId = null;
    /** Narrows the outlet rather than replacing it — see the migration. */
    public string $sectionId = '';
    public ?int $traineeId = null;

    public ?string $dueOn = null;
    public bool $isMandatory = true;
    public string $note = '';

    public function mount(): void
    {
        $this->requireActiveCompany();
    }

    public function openCreate(): void
    {
        $this->reset(['courseId', 'pathId', 'quizId', 'outletId', 'sectionId', 'traineeId', 'dueOn', 'note']);
        $this->targetType   = 'course';
        $this->audienceType = 'outlet';
        $this->isMandatory  = true;
        $this->resetErrorBag();
        $this->showModal    = true;
    }

    public function save(): void
    {
        $this->authorize('training.assign');

        $this->validate([
            'courseId'  => [$this->targetType === 'course' ? 'required' : 'nullable', 'integer'],
            'pathId'    => [$this->targetType === 'path' ? 'required' : 'nullable', 'integer'],
            'quizId'    => [$this->targetType === 'quiz' ? 'required' : 'nullable', 'integer'],
            'outletId'  => [$this->audienceType === 'outlet' ? 'required' : 'nullable', $this->outletExistsRule()],
            // Company-scoped: a section id is client-supplied like any other
            // field, and the <select> is not the control.
            'sectionId' => ['nullable', Rule::exists('sections', 'id')
                ->where('company_id', Auth::user()->company_id)],
            'traineeId' => [$this->audienceType === 'trainee' ? 'required' : 'nullable', 'integer'],
            'dueOn'     => ['nullable', 'date'],
            'note'      => ['nullable', 'string', 'max:500'],
        ], [], [
            'courseId'  => 'course',
            'pathId'    => 'learning path',
            'quizId'    => 'quiz',
            'outletId'  => 'outlet',
            'sectionId' => 'section',
            'traineeId' => 'staff member',
        ]);

        // Re-resolved through the scoped models, so an id from another company
        // 404s rather than being written.
        $courseId = $this->targetType === 'course'
            ? TrainingCourse::findOrFail($this->courseId)->id
            : null;
        $pathId = $this->targetType === 'path'
            ? TrainingPath::findOrFail($this->pathId)->id
            : null;
        $quizId = $this->targetType === 'quiz'
            ? TrainingQuiz::findOrFail($this->quizId)->id
            : null;

        $employeeId = $this->audienceType === 'trainee'
            ? Employee::where('company_id', Auth::user()->company_id)->findOrFail($this->traineeId)->id
            : null;

        $isOutlet = $this->audienceType === 'outlet';

        TrainingAssignment::create([
            'company_id'         => Auth::user()->company_id,
            'training_course_id' => $courseId,
            'training_path_id'   => $pathId,
            'training_quiz_id'   => $quizId,
            'employee_id'        => $employeeId,
            'outlet_id'          => $isOutlet ? $this->outletId : null,
            // Only meaningful alongside an outlet audience; an individual
            // assignment already names the person, section and all.
            'section_id'         => $isOutlet ? ($this->sectionId ?: null) : null,
            'due_on'             => $this->dueOn ?: null,
            'is_mandatory'       => $this->isMandatory,
            'note'               => $this->note ?: null,
            'assigned_by'        => Auth::id(),
        ]);

        $this->showModal = false;

        session()->flash('success', 'Assignment created.');
    }

    public function delete(int $id): void
    {
        $this->authorize('training.assign');

        TrainingAssignment::findOrFail($id)->delete();

        session()->flash('success', 'Assignment removed.');
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        $assignments = TrainingAssignment::query()
            ->with([
                'course:id,title', 'path:id,name', 'quiz:id,title',
                'employee:id,name', 'outlet:id,name', 'section:id,name',
                'assignedBy:id,name',
            ])
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->latest('id')
            ->paginate(20);

        $courses = TrainingCourse::published()->orderBy('title')->get(['id', 'title']);
        $paths   = TrainingPath::published()->orderBy('name')->get(['id', 'name']);
        // Published only: assigning a draft would be a requirement nobody can
        // meet, because the trainee's course page never offers it.
        $quizzes = TrainingQuiz::published()->with('course:id,title')
            ->orderBy('title')->get(['id', 'title', 'training_course_id', 'language', 'section_id']);
        $sections = \App\Models\Section::active()->ordered()->get(['id', 'name']);
        $outlets = Outlet::where('company_id', $companyId)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name']);
        // Staff, not LMS trainees: training is reached with the staff PIN now,
        // so the people who can be assigned it are the people on the payroll.
        $trainees = Employee::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'staff_id']);

        return view('livewire.training.assignments', compact(
            'assignments', 'courses', 'paths', 'quizzes', 'sections', 'outlets', 'trainees'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Assignments']);
    }
}
