<?php

namespace App\Livewire\Staff\Training;

use App\Models\TrainingAttempt;
use App\Models\TrainingCourse;
use App\Livewire\Clock\Staff\StaffComponent;

/**
 * Read the material, then take the quiz.
 *
 * The course is re-fetched through this employee's own outlet scope on every
 * render, not just at mount: a URL is a URL, the posting that made this
 * readable can change, and a bookmarked page must stop working when it does.
 */
class CourseView extends StaffComponent
{
    public int $courseId;

    public function mount(int $id): void
    {
        $this->courseId = $this->course($id)->id;
    }

    /**
     * The course, with only the quizzes THIS person should be offered.
     *
     * It used to load every published quiz and the page took the first one,
     * which was fine while a course had exactly one. It does not any more: the
     * same material now carries a kitchen questionnaire and a floor one, and an
     * English set beside a Malay set. Taking the first would have handed a
     * waiter the chef's quiz because it happened to have the lower id.
     *
     * Section filtering happens in SQL — untagged quizzes count as everybody's,
     * see TrainingQuiz::scopeForSection — and whatever survives is offered as a
     * choice rather than picked for them. Two languages is a decision only the
     * reader can make.
     */
    private function course(?int $id = null): TrainingCourse
    {
        $employee = $this->staff();

        return TrainingCourse::query()
            ->where('company_id', $employee->company_id)
            ->published()
            ->visibleToOutlets($employee->trainingOutletIds())
            ->with(['quizzes' => fn ($q) => $q
                ->where('status', 'published')
                ->forAudience($employee->section_id, $employee->trainingOutletIds())
                ->with('sections:id,name')
                ->withCount('questions')
                ->orderBy('id')])
            ->findOrFail($id ?? $this->courseId);
    }

    /** Same extraction the SOP view uses, so one video field serves both. */
    public function videoData(?string $url): ?array
    {
        if (! $url) {
            return null;
        }

        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return ['type' => 'youtube', 'id' => $m[1]];
        }

        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return ['type' => 'vimeo', 'id' => $m[1]];
        }

        return null;
    }

    public function render()
    {
        $employee = $this->staff();
        $course   = $this->course();
        $quizzes  = $course->quizzes;

        // Best attempt per quiz, so each card can say where they got to. One
        // query for the lot rather than one per quiz.
        $best = TrainingAttempt::query()
            ->whereIn('training_quiz_id', $quizzes->pluck('id'))
            ->where('employee_id', $employee->id)
            ->completed()
            ->get()
            ->groupBy('training_quiz_id')
            ->map(fn ($attempts) => $attempts->sortByDesc('percent')->first());

        return view('livewire.staff.training.course-view', [
            'course'   => $course,
            'quizzes'  => $quizzes,
            'best'     => $best,
            'employee' => $employee,
            'video'    => $this->videoData($course->video_url),
        ])->layout('layouts.clock-staff', $this->shell($course->title));
    }
}
