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
        'week'  => 'This week',
        'month' => 'This month',
        'year'  => 'This year',
        'all'   => 'All time',
    ];

    /**
     * @return array{start: ?Carbon, end: ?Carbon}
     */
    public function range(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'week'  => ['start' => $now->copy()->startOfWeek(), 'end' => $now->copy()->endOfWeek()],
            'month' => ['start' => $now->copy()->startOfMonth(), 'end' => $now->copy()->endOfMonth()],
            'year'  => ['start' => $now->copy()->startOfYear(), 'end' => $now->copy()->endOfYear()],
            default => ['start' => null, 'end' => null],
        };
    }

    /**
     * The board.
     *
     * @return Collection<int, array{
     *     rank: int, trainee_id: int, name: string, outlet: ?string,
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
            ->whereNotNull('lms_user_id')
            ->when($start, fn ($q) => $q->where('completed_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('completed_at', '<=', $end))
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->when($quizId, fn ($q) => $q->where('training_quiz_id', $quizId))
            ->with(['trainee:id,name,outlet_id', 'trainee.outlet:id,name'])
            ->get();

        return $attempts
            ->groupBy('lms_user_id')
            ->map(function (Collection $theirs) {
                // Best per quiz — see the class note.
                $best = $theirs->groupBy('training_quiz_id')
                    ->map(fn (Collection $forQuiz) => $forQuiz->sortByDesc('score')->first())
                    ->values();

                $trainee = $theirs->first()->trainee;

                $questions = (int) $best->sum('question_count');
                $correct   = (int) $best->sum('correct_count');

                return [
                    'trainee_id' => (int) $theirs->first()->lms_user_id,
                    'name'       => $trainee->name ?? 'Removed trainee',
                    'outlet'     => $trainee?->outlet?->name,
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
     * One trainee's position, even when they are off the end of the board.
     *
     * A staff member who is 63rd still wants to know they are 63rd — "not in
     * the top 50" tells them nothing about whether they are close.
     *
     * @return array{rank: int, of: int}|null
     */
    public function positionOf(int $companyId, int $traineeId, string $period = 'month', ?int $outletId = null): ?array
    {
        $full = $this->board($companyId, $period, $outletId, null, PHP_INT_MAX);
        $row  = $full->firstWhere('trainee_id', $traineeId);

        return $row ? ['rank' => $row['rank'], 'of' => $full->count()] : null;
    }
}
