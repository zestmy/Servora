<?php

namespace App\Livewire\Lms;

use App\Models\TrainingAttempt;
use App\Models\TrainingCourse;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Read the material, then take the quiz.
 *
 * The course is re-fetched through the trainee's own outlet scope on every
 * load, not just at mount, because a URL is a URL: the outlet grant that made
 * this readable can be taken away, and a bookmarked page must stop working when
 * it does.
 */
class CourseView extends Component
{
    public int $courseId;

    public function mount(int $id): void
    {
        $this->courseId = $this->course($id)->id;
    }

    private function course(?int $id = null): TrainingCourse
    {
        $trainee = Auth::guard('lms')->user();

        return TrainingCourse::query()
            ->where('company_id', $trainee->company_id)
            ->published()
            ->visibleToOutlets($trainee->accessibleOutletIds())
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

    public function render()
    {
        $trainee = Auth::guard('lms')->user();
        $course  = $this->course();
        $quiz    = $course->quizzes->first();

        $attempts = $quiz
            ? TrainingAttempt::where('training_quiz_id', $quiz->id)
                ->where('lms_user_id', $trainee->id)
                ->completed()
                ->orderByDesc('completed_at')
                ->get()
            : collect();

        return view('livewire.lms.course-view', [
            'course'    => $course,
            'quiz'      => $quiz,
            'attempts'  => $attempts,
            'best'      => $attempts->sortByDesc('percent')->first(),
            'remaining' => $quiz?->attemptsRemaining($trainee->id),
            'video'     => $this->videoData($course->video_url),
        ])->layout('layouts.lms', ['title' => $course->title]);
    }
}
