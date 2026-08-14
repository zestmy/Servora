<?php

namespace App\Services\Training;

use App\Models\LmsUser;
use App\Models\TrainingAnswer;
use App\Models\TrainingAssignment;
use App\Models\TrainingAttempt;
use App\Models\TrainingCourse;
use Illuminate\Support\Collection;

/**
 * One staff member's training record.
 *
 * The report card answers three questions a manager actually asks: is this
 * person up to date, are they getting better, and what do they not know. The
 * third is the one worth building for — a score tells you somebody is at 62%
 * and nothing about what to teach them next, whereas "Allergens: 3 of 9" tells
 * you what to say at handover tomorrow.
 *
 * WEAK TOPICS ARE COUNTED ACROSS EVERY ANSWER, not best-of like the
 * leaderboard. The leaderboard is a game and rewards your best day; the report
 * card is a diagnosis, and a question somebody got wrong twice and right once
 * is not a topic they have mastered.
 */
class ReportCardService
{
    /** Below this share correct, a topic is flagged as needing work. */
    public const WEAK_THRESHOLD = 0.7;

    /** A topic needs this many answers before it is worth calling weak. */
    public const MIN_ANSWERS_FOR_TOPIC = 2;

    public function __construct(private LeaderboardService $leaderboard)
    {
    }

    /**
     * @return array{
     *   trainee: LmsUser, attempts: int, passed: int, average: float,
     *   points: int, best_streak: int, last_activity: ?\Illuminate\Support\Carbon,
     *   topics: Collection, weak_topics: Collection, recent: Collection,
     *   outstanding: Collection, certificates: Collection, position: ?array
     * }
     */
    public function for(LmsUser $trainee): array
    {
        $attempts = TrainingAttempt::query()
            ->where('lms_user_id', $trainee->id)
            ->completed()
            ->with(['quiz:id,title,training_course_id', 'quiz.course:id,title'])
            ->orderByDesc('completed_at')
            ->get();

        $topics = $this->topicBreakdown($attempts->pluck('id')->all());

        return [
            'trainee'       => $trainee,
            'attempts'      => $attempts->count(),
            'passed'        => $attempts->where('passed', true)->count(),
            'average'       => $attempts->isNotEmpty() ? round($attempts->avg('percent'), 1) : 0.0,
            'points'        => (int) $attempts->sum('score'),
            'best_streak'   => (int) ($trainee->attempts()->with('player')->get()
                ->max(fn ($a) => $a->player?->best_streak ?? 0) ?? 0),
            'last_activity' => $attempts->first()?->completed_at,
            'topics'        => $topics,
            'weak_topics'   => $topics->filter(
                fn (array $t) => $t['answered'] >= self::MIN_ANSWERS_FOR_TOPIC
                    && $t['accuracy'] < self::WEAK_THRESHOLD * 100
            )->values(),
            'recent'        => $attempts->take(10)->values(),
            'outstanding'   => $this->outstanding($trainee),
            'certificates'  => $trainee->certificates()->with('course:id,title')->latest('issued_at')->get(),
            'position'      => $this->leaderboard->positionOf($trainee->company_id, $trainee->id, 'month'),
        ];
    }

    /**
     * Accuracy per topic tag.
     *
     * Questions with no topic are folded into "General" rather than dropped:
     * hand-written questions rarely carry one, and silently omitting them would
     * make the breakdown disagree with the attempt totals directly above it.
     *
     * @param  array<int, int>  $attemptIds
     * @return Collection<int, array{topic: string, answered: int, correct: int, accuracy: float}>
     */
    public function topicBreakdown(array $attemptIds): Collection
    {
        if ($attemptIds === []) {
            return collect();
        }

        return TrainingAnswer::query()
            ->whereIn('training_attempt_id', $attemptIds)
            ->with('question:id,topic')
            ->get()
            ->groupBy(fn (TrainingAnswer $a) => $a->question?->topic ?: 'General')
            ->map(function (Collection $answers, string $topic) {
                $correct = $answers->where('is_correct', true)->count();

                return [
                    'topic'    => $topic,
                    'answered' => $answers->count(),
                    'correct'  => $correct,
                    'accuracy' => round($correct / max(1, $answers->count()) * 100, 1),
                ];
            })
            ->sortBy('accuracy')
            ->values();
    }

    /**
     * What this trainee still owes.
     *
     * An assignment is outstanding until there is a PASSED attempt at one of
     * the target's quizzes. Completing a course by reading it is not evidence
     * of anything — the pass is.
     *
     * @return Collection<int, array{assignment: TrainingAssignment, title: string, due_on: mixed, overdue: bool}>
     */
    public function outstanding(LmsUser $trainee): Collection
    {
        $assignments = TrainingAssignment::query()
            ->forTrainee($trainee)
            ->with(['course.quizzes:id,training_course_id', 'path.items.course.quizzes:id,training_course_id'])
            ->get();

        $passedQuizIds = TrainingAttempt::query()
            ->where('lms_user_id', $trainee->id)
            ->where('passed', true)
            ->completed()
            ->pluck('training_quiz_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $assignments
            ->map(function (TrainingAssignment $assignment) use ($passedQuizIds) {
                $courses = $assignment->course
                    ? collect([$assignment->course])
                    : ($assignment->path?->items->pluck('course')->filter() ?? collect());

                $required = $courses
                    ->flatMap(fn (TrainingCourse $c) => $c->quizzes->pluck('id'))
                    ->map(fn ($id) => (int) $id);

                // A course with no quiz cannot be "passed", so it stays
                // outstanding — which is the honest answer, and shows up as the
                // nudge to add a quiz to it.
                $done = $required->isNotEmpty()
                    && $required->every(fn (int $id) => in_array($id, $passedQuizIds, true));

                return $done ? null : [
                    'assignment' => $assignment,
                    'title'      => $assignment->targetName(),
                    'due_on'     => $assignment->due_on,
                    'overdue'    => $assignment->due_on !== null && $assignment->due_on->isPast(),
                ];
            })
            ->filter()
            ->sortBy(fn (array $row) => $row['due_on']?->timestamp ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * The whole roster at a glance, for the report-card list.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function roster(int $companyId, ?int $outletId = null, string $search = ''): Collection
    {
        $trainees = LmsUser::query()
            ->where('company_id', $companyId)
            ->approved()
            ->when($search, fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->with('outlet:id,name')
            ->orderBy('name')
            ->get();

        if ($outletId) {
            $trainees = $trainees->filter(
                fn (LmsUser $t) => in_array($outletId, $t->accessibleOutletIds(), true)
                    || $t->accessibleOutletIds() === []
            )->values();
        }

        $stats = TrainingAttempt::query()
            ->where('company_id', $companyId)
            ->completed()
            ->whereIn('lms_user_id', $trainees->pluck('id'))
            ->get()
            ->groupBy('lms_user_id');

        return $trainees->map(function (LmsUser $trainee) use ($stats) {
            $theirs = $stats->get($trainee->id, collect());

            return [
                'trainee'  => $trainee,
                'attempts' => $theirs->count(),
                'average'  => $theirs->isNotEmpty() ? round($theirs->avg('percent'), 1) : null,
                'passed'   => $theirs->where('passed', true)->count(),
                'points'   => (int) $theirs->sum('score'),
                'last'     => $theirs->max('completed_at'),
            ];
        });
    }
}
