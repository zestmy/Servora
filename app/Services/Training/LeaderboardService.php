<?php

namespace App\Services\Training;

use App\Models\TrainingAttempt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Who is winning.
 *
 * ONE SCORE PER QUIZ PER PERSON — their best. Summing every attempt would rank
 * by persistence rather than knowledge, and the quiz that allows unlimited
 * retries would become the only one worth playing. Best-of also makes retrying
 * safe, which is what you want: a trainee who is unsure should be practising,
 * not protecting a score.
 *
 * WHY THE GROUPING IS DONE IN PHP. The tests run on SQLite and production is
 * MySQL, and a leaderboard is exactly the query that tempts you into
 * DATE_FORMAT/YEAR() — which makes the screen untestable. Attempts are filtered
 * to a date range in SQL, where both engines agree, and the best-per-person
 * fold happens in a collection.
 */
class LeaderboardService
{
    public const PERIODS = [
        'week'      => 'This week',
        'last_week' => 'Last week',
        'month'     => 'This month',
        'year'      => 'This year',
        'all'       => 'All time',
    ];

    /**
     * @return array{start: ?Carbon, end: ?Carbon}
     */
    public function range(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'week'  => ['start' => $now->copy()->startOfWeek(), 'end' => $now->copy()->endOfWeek()],
            /*
             * Monday to Sunday, the same week boundary every other screen in
             * the product uses — see QuickDateRangeTest, which exists because
             * two screens once disagreed about when a week starts and the
             * numbers could not be reconciled by anybody looking at them.
             *
             * Last week matters here more than it does elsewhere: a board that
             * only ever shows the CURRENT week resets to empty every Monday
             * morning, which is exactly when a manager wants to see who
             * finished and who did not.
             */
            'last_week' => [
                'start' => $now->copy()->subWeek()->startOfWeek(),
                'end'   => $now->copy()->subWeek()->endOfWeek(),
            ],
            'month' => ['start' => $now->copy()->startOfMonth(), 'end' => $now->copy()->endOfMonth()],
            'year'  => ['start' => $now->copy()->startOfYear(), 'end' => $now->copy()->endOfYear()],
            default => ['start' => null, 'end' => null],
        };
    }

    /**
     * Where somebody stands on ONE quiz, right now, mid-attempt.
     *
     * This is what makes a self-paced quiz feel like a race rather than a form.
     * A score that only appears at the end is a mark; a position that moves
     * between questions is a game, and the person two places above you is the
     * reason you read the next one properly.
     *
     * Their own running total is passed in rather than read: the attempt is
     * still open, so the rows are the only truth about it and the caller has
     * just written one. Everyone else is compared on their BEST COMPLETED
     * score, which is the same rule the main board uses — see the class note.
     *
     * @return array{rank: int, of: int, ahead: ?string, gap: int}
     */
    public function standingOnQuiz(int $quizId, int $employeeId, int $myScore): array
    {
        $others = TrainingAttempt::query()
            ->where('training_quiz_id', $quizId)
            ->where('employee_id', '!=', $employeeId)
            ->completed()
            ->with('employee:id,name')
            ->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $theirs) => $theirs->sortByDesc('score')->first())
            ->sortByDesc('score')
            ->values();

        // Everyone strictly ahead. A tie leaves you in front of them, which is
        // the generous reading and the one that keeps a climb from stalling on
        // an equal score.
        $ahead = $others->filter(fn ($a) => (int) $a->score > $myScore)->values();

        $justAbove = $ahead->last();

        return [
            'rank'  => $ahead->count() + 1,
            'of'    => $others->count() + 1,
            'ahead' => $justAbove?->employee?->name,
            'gap'   => $justAbove ? max(0, (int) $justAbove->score - $myScore) : 0,
        ];
    }

    /**
     * The board.
     *
     * @return Collection<int, array{
     *     rank: int, employee_id: int, name: string, outlet: ?string,
     *     score: int, quizzes: int, accuracy: float, passed: int
     * }>
     */
    public function board(
        int $companyId,
        string $period = 'month',
        ?int $outletId = null,
        ?int $quizId = null,
        int $limit = 50,
    ): Collection {
        ['start' => $start, 'end' => $end] = $this->range($period);

        $attempts = TrainingAttempt::query()
            ->where('company_id', $companyId)
            ->completed()
            ->whereNotNull('employee_id')
            ->when($start, fn ($q) => $q->where('completed_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('completed_at', '<=', $end))
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->when($quizId, fn ($q) => $q->where('training_quiz_id', $quizId))
            ->with(['employee:id,name,outlet_id,photo_path', 'employee.outlet:id,name'])
            ->get();

        return $attempts
            ->groupBy('employee_id')
            ->map(function (Collection $theirs) {
                // Best per quiz — see the class note.
                $best = $theirs->groupBy('training_quiz_id')
                    ->map(fn (Collection $forQuiz) => $forQuiz->sortByDesc('score')->first())
                    ->values();

                $employee = $theirs->first()->employee;

                $questions = (int) $best->sum('question_count');
                $correct   = (int) $best->sum('correct_count');

                return [
                    'employee_id' => (int) $theirs->first()->employee_id,
                    'name'       => $employee->name ?? 'Removed staff member',
                    'photo'      => $employee?->photo_path,
                    'outlet'     => $employee?->outlet?->name,
                    'score'      => (int) $best->sum('score'),
                    'quizzes'    => $best->count(),
                    'accuracy'   => $questions > 0 ? round($correct / $questions * 100, 1) : 0.0,
                    'passed'     => $best->where('passed', true)->count(),
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->take($limit)
            // Rank after the sort, so it is the position on THIS board rather
            // than a property of the row.
            ->map(fn (array $row, int $i) => ['rank' => $i + 1] + $row)
            ->values();
    }

    /**
     * Active staff with nothing finished in the period.
     *
     * The board answers "who is winning"; on a floor of fifty-odd people the
     * more useful management question is "who has not started", and it cannot
     * be read off a list of the people who have. A manager wanting to chase
     * three names should not have to diff a roster against a leaderboard.
     *
     * A NOTE ON WHAT THIS IS. It is a list of colleagues who have not done
     * something, visible to their colleagues, and that is a real thing to be
     * careful with — the same instinct that keeps negative scores off the
     * board. Two things keep it fair: it says NOTHING beyond "not yet",
     * carrying no score, no rank and no history, and it disappears the moment
     * somebody finishes one quiz. It is a nudge list, not a wall of shame, and
     * the copy on the screen has to keep it that way.
     *
     * Ordered by name rather than by anything derived, so it reads as a roster
     * and not as a ranking of laziness.
     *
     * @return Collection<int, \App\Models\Employee>
     */
    public function notStarted(int $companyId, string $period = 'month', ?int $outletId = null): Collection
    {
        ['start' => $start, 'end' => $end] = $this->range($period);

        $done = TrainingAttempt::query()
            ->where('company_id', $companyId)
            ->completed()
            ->whereNotNull('employee_id')
            ->when($start, fn ($q) => $q->where('completed_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('completed_at', '<=', $end))
            ->pluck('employee_id')
            ->unique()
            ->all();

        return \App\Models\Employee::query()
            ->withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->whereNotIn('id', $done ?: [0])
            ->with('outlet:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'outlet_id', 'photo_path']);
    }

    /**
     * One person's position, even when they are off the end of the board.
     *
     * A staff member who is 63rd still wants to know they are 63rd — "not in
     * the top 50" tells them nothing about whether they are close.
     *
     * @return array{rank: int, of: int}|null
     */
    public function positionOf(int $companyId, int $employeeId, string $period = 'month', ?int $outletId = null): ?array
    {
        $full = $this->board($companyId, $period, $outletId, null, PHP_INT_MAX);
        $row  = $full->firstWhere('employee_id', $employeeId);

        return $row ? ['rank' => $row['rank'], 'of' => $full->count()] : null;
    }
}
