<?php

namespace App\Livewire\Training;

use App\Models\TrainingCourse;
use App\Traits\RequiresActiveCompany;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The course library.
 *
 * Deliberately a list and not a grid of cover art: the thing an author is
 * looking for is "does this course have a quiz yet, and is it published", and
 * both of those are text. The cover matters to the trainee, who sees it in the
 * portal, not to the person maintaining the catalogue.
 */
class Courses extends Component
{
    use RequiresActiveCompany;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'cat', except: '')]
    public string $categoryFilter = '';

    #[Url(as: 'compliance', except: false)]
    public bool $complianceOnly = false;

    public function mount(): void
    {
        $this->requireActiveCompany();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedComplianceOnly(): void
    {
        $this->resetPage();
    }

    public function publish(int $id): void
    {
        $this->authorize('training.manage');

        $course = TrainingCourse::findOrFail($id);

        // Publishing material nobody can read is the one state worth stopping:
        // it puts an empty page in front of a trainee who was told to study it.
        if (blank($course->content)) {
            session()->flash('error', 'Add some material before publishing — the course page would be empty.');

            return;
        }

        $course->update(['status' => 'published']);

        session()->flash('success', "\"{$course->title}\" is now live in the training portal.");
    }

    public function unpublish(int $id): void
    {
        $this->authorize('training.manage');

        $course = TrainingCourse::findOrFail($id);
        $course->update(['status' => 'draft']);

        session()->flash('success', "\"{$course->title}\" is back to draft.");
    }

    public function delete(int $id): void
    {
        $this->authorize('training.manage');

        $course = TrainingCourse::findOrFail($id);
        $title  = $course->title;
        $course->delete();

        session()->flash('success', "\"{$title}\" was deleted.");
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        $courses = TrainingCourse::query()
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('title', 'like', "%{$this->search}%")
                   ->orWhere('summary', 'like', "%{$this->search}%");
            }))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->when($this->complianceOnly, fn ($q) => $q->where('is_compliance', true))
            ->withCount(['quizzes', 'outlets'])
            ->with(['sourceRecipe:id,name', 'quizzes:id,training_course_id,status'])
            ->latest('id')
            ->paginate(15);

        $categories = TrainingCourse::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $stats = [
            'total'      => TrainingCourse::count(),
            'published'  => TrainingCourse::where('status', 'published')->count(),
            'compliance' => TrainingCourse::where('is_compliance', true)->count(),
            'no_quiz'    => TrainingCourse::doesntHave('quizzes')->count(),
        ];

        return view('livewire.training.courses', compact('courses', 'categories', 'stats', 'companyId'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Courses']);
    }
}
