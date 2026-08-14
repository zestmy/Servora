<?php

namespace App\Livewire\Training;

use App\Models\TrainingQuiz;
use App\Traits\RequiresActiveCompany;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every quiz in the company, with the two numbers that decide whether it is
 * working: how many questions it has, and how many people have taken it.
 *
 * A published quiz with no attempts after a fortnight is not a quiz problem, it
 * is an assignment problem — which is why the count is here rather than buried
 * in a report.
 */
class Quizzes extends Component
{
    use RequiresActiveCompany;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

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

    public function publish(int $id): void
    {
        $this->authorize('training.manage');

        $quiz = TrainingQuiz::withCount('questions')->findOrFail($id);

        if ($quiz->questions_count === 0) {
            session()->flash('error', 'A quiz with no questions cannot be published.');

            return;
        }

        $quiz->update(['status' => 'published']);

        session()->flash('success', "\"{$quiz->title}\" is now available to trainees.");
    }

    public function unpublish(int $id): void
    {
        $this->authorize('training.manage');

        TrainingQuiz::findOrFail($id)->update(['status' => 'draft']);

        session()->flash('success', 'Quiz returned to draft.');
    }

    public function delete(int $id): void
    {
        $this->authorize('training.manage');

        $quiz = TrainingQuiz::findOrFail($id);
        $quiz->delete();

        session()->flash('success', "\"{$quiz->title}\" was deleted.");
    }

    public function render()
    {
        $quizzes = TrainingQuiz::query()
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->withCount(['questions', 'attempts', 'sessions'])
            ->with(['course:id,title', 'section:id,name'])
            ->latest('id')
            ->paginate(15);

        return view('livewire.training.quizzes', compact('quizzes'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Quizzes']);
    }
}
