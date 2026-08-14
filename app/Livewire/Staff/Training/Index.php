<?php

namespace App\Livewire\Staff\Training;

use App\Models\TrainingAttempt;
use App\Models\TrainingCourse;
use App\Models\TrainingSession;
use App\Livewire\Clock\Staff\StaffComponent;
use App\Services\Training\ReportCardService;
use Livewire\Attributes\Url;

/**
 * What this employee has to learn, and what they can learn.
 *
 * Outstanding work first, catalogue second. Somebody opening this between
 * covers has three minutes, and the honest answer to "what should I do now" is
 * the thing with a date on it rather than the newest course.
 */
class Index extends StaffComponent
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function render(ReportCardService $reportCards)
    {
        $employee  = $this->staff();
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

        /*
         * Challenges open right now, above everything else on the screen.
         *
         * Scoped to this person's outlet the same way courses are: a challenge
         * with no outlet is company-wide, one naming a branch is for that
         * branch. Those they have already finished are dropped rather than
         * shown as done — a competition they cannot re-enter is not something
         * to keep offering.
         */
        $taken = TrainingAttempt::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('training_session_id')
            ->whereNotNull('completed_at')
            ->pluck('training_session_id')
            ->all();

        $challenges = TrainingSession::query()
            ->where('company_id', $employee->company_id)
            ->openNow()
            ->when($employee->outlet_id, fn ($q) => $q->where(fn ($o) => $o
                ->whereNull('outlet_id')->orWhere('outlet_id', $employee->outlet_id)))
            ->whereNotIn('id', $taken ?: [0])
            ->with('quiz:id,title,language,pass_mark')
            ->orderBy('closes_at')
            ->get()
            ->filter(fn (TrainingSession $s) => $s->quiz !== null);

        return view('livewire.staff.training.index', [
            'employee'    => $employee,
            'courses'     => $courses,
            'best'        => $best,
            'challenges'  => $challenges,
            'outstanding' => $reportCards->outstanding($employee),
        ])->layout('layouts.clock-staff', $this->shell('Learn'));
    }
}
