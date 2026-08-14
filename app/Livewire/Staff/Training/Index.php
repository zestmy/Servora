<?php

namespace App\Livewire\Staff\Training;

use App\Models\TrainingAttempt;
use App\Models\TrainingCourse;
use App\Services\Staff\StaffSession;
use App\Services\Training\ReportCardService;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * What this employee has to learn, and what they can learn.
 *
 * Outstanding work first, catalogue second. Somebody opening this between
 * covers has three minutes, and the honest answer to "what should I do now" is
 * the thing with a date on it rather than the newest course.
 */
class Index extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function render(StaffSession $staff, ReportCardService $reportCards)
    {
        $employee  = $staff->employee();
        $outletIds = $employee->trainingOutletIds();

        $courses = TrainingCourse::query()
            ->where('company_id', $employee->company_id)
            ->published()
            ->visibleToOutlets($outletIds)
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('title', 'like', "%{$this->search}%")
                   ->orWhere('summary', 'like', "%{$this->search}%");
            }))
            ->with(['quizzes' => fn ($q) => $q->where('status', 'published')])
            ->orderBy('title')
            ->get();

        // Best percent per quiz, so a card can say "passed 90%" rather than
        // just "done". One query for the lot rather than one per card.
        $best = TrainingAttempt::query()
            ->where('employee_id', $employee->id)
            ->completed()
            ->get()
            ->groupBy('training_quiz_id')
            ->map(fn ($attempts) => $attempts->sortByDesc('percent')->first());

        return view('livewire.staff.training.index', [
            'employee'    => $employee,
            'courses'     => $courses,
            'best'        => $best,
            'outstanding' => $reportCards->outstanding($employee),
        ])->layout('layouts.clock-staff', ['title' => 'Learn']);
    }
}
