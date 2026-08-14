<?php

namespace App\Livewire\Lms;

use App\Models\TrainingAssignment;
use App\Models\TrainingAttempt;
use App\Models\TrainingCourse;
use App\Models\TrainingPath;
use App\Services\Training\ReportCardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * What a trainee sees when they open Training.
 *
 * Outstanding work comes FIRST, before the catalogue. Somebody opening this on
 * a break has three minutes, and the honest answer to "what should I do now" is
 * the thing with a due date on it — not the newest course.
 */
class Courses extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'cat', except: '')]
    public string $categoryFilter = '';

    public function render(ReportCardService $reportCards)
    {
        $trainee   = Auth::guard('lms')->user();
        $outletIds = $trainee->accessibleOutletIds();

        $courses = TrainingCourse::query()
            ->where('company_id', $trainee->company_id)
            ->published()
            ->visibleToOutlets($outletIds)
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('title', 'like', "%{$this->search}%")
                   ->orWhere('summary', 'like', "%{$this->search}%");
            }))
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->with(['quizzes' => fn ($q) => $q->where('status', 'published')])
            ->orderBy('title')
            ->get();

        // Best percent per quiz, so a card can say "passed 90%" rather than
        // just "done". One query for the lot rather than one per card.
        $best = TrainingAttempt::query()
            ->where('lms_user_id', $trainee->id)
            ->completed()
            ->get()
            ->groupBy('training_quiz_id')
            ->map(fn ($attempts) => $attempts->sortByDesc('percent')->first());

        $categories = TrainingCourse::query()
            ->where('company_id', $trainee->company_id)
            ->published()
            ->visibleToOutlets($outletIds)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $outstanding = $reportCards->outstanding($trainee);

        $paths = TrainingPath::query()
            ->where('company_id', $trainee->company_id)
            ->published()
            ->whereHas('assignments', fn ($q) => $q->forTrainee($trainee))
            ->with('items.course:id,title')
            ->orderBy('name')
            ->get();

        $dueSoon = TrainingAssignment::query()
            ->where('company_id', $trainee->company_id)
            ->forTrainee($trainee)
            ->whereNotNull('due_on')
            ->count();

        return view('livewire.lms.courses', compact(
            'courses', 'categories', 'best', 'outstanding', 'paths', 'dueSoon', 'trainee'
        ))->layout('layouts.lms', ['title' => 'Training']);
    }
}
