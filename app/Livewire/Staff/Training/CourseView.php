<?php

namespace App\Livewire\Staff\Training;

use App\Models\TrainingAttempt;
use App\Models\TrainingCourse;
use App\Services\Staff\StaffSession;
use Livewire\Component;

/**
 * Read the material, then take the quiz.
 *
 * The course is re-fetched through this employee's own outlet scope on every
 * render, not just at mount: a URL is a URL, the posting that made this
 * readable can change, and a bookmarked page must stop working when it does.
 */
class CourseView extends Component
{
    public int $courseId;

    public function mount(int $id, StaffSession $staff): void
    {
        $this->courseId = $this->course($staff, $id)->id;
    }

    private function course(StaffSession $staff, ?int $id = null): TrainingCourse
    {
        $employee = $staff->employee();

        return TrainingCourse::query()
            ->where('company_id', $employee->company_id)
            ->published()
            ->visibleToOutlets($employee->trainingOutletIds())
            ->with(['quizzes' => fn ($q) => $q->where('status', 'published')->withCount('questions')])
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

    public function render(StaffSession $staff)
    {
        $employee = $staff->employee();
        $course   = $this->course($staff);
        $quiz     = $course->quizzes->first();

        $attempts = $quiz
            ? TrainingAttempt::where('training_quiz_id', $quiz->id)
                ->where('employee_id', $employee->id)
                ->completed()
                ->orderByDesc('completed_at')
                ->get()
            : collect();

        return view('livewire.staff.training.course-view', [
            'course'    => $course,
            'quiz'      => $quiz,
            'attempts'  => $attempts,
            'best'      => $attempts->sortByDesc('percent')->first(),
            'remaining' => $quiz?->attemptsRemaining($employee->id),
            'video'     => $this->videoData($course->video_url),
        ])->layout('layouts.clock-staff', ['title' => $course->title]);
    }
}
