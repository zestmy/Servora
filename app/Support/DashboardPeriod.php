<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The window a dashboard is reporting on, and the window it compares against.
 *
 * Every dashboard builder used to open with `$now = now()` and query
 * `whereMonth(...)->whereYear(...)`, which hardwired all seven of them to the
 * current calendar month. There was no way to ask for last month — the single
 * most common follow-up question any figure on the page provokes.
 *
 * What each named range MEANS is not decided here: HasQuickDateRanges already
 * owns that, across four screens, precisely so "last week" cannot come to mean
 * two different weeks on two pages people compare. This class complements it
 * with the part a filtered list never needs and a dashboard cannot do without
 * — the prior window to measure against.
 *
 * The prior window is not simply "the same number of days, immediately
 * before". For a month in progress that rule slides backwards across the
 * month boundary and compares 12 days of August against 5 of August and 7 of
 * July, which is not what anyone means by "vs last month". Month ranges get
 * the matching stretch of the previous month; rolling ranges get the
 * equal-length window behind them, where that rule is the right one.
 */
final class DashboardPeriod
{
    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly CarbonImmutable $priorStart,
        public readonly CarbonImmutable $priorEnd,
        public readonly string $priorLabel,
        public readonly int $daysElapsed,
        public readonly int $daysInPeriod,
    ) {
    }

    /**
     * The ranges the dashboard offers. A subset of HasQuickDateRanges::
     * allQuickRanges() — a dashboard is a standing view, so "yesterday" and
     * "all time" are not useful here, and a nine-pill row is a row you read
     * instead of scan.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'last_7'     => 'Last 7 days',
            'last_30'    => 'Last 30 days',
        ];
    }

    public static function make(?string $key): self
    {
        $key = array_key_exists((string) $key, self::options()) ? (string) $key : 'this_month';

        $today = CarbonImmutable::today();

        return match ($key) {
            'last_month' => self::month($key, $today->subMonthNoOverflow(), $today),
            'last_7'     => self::rolling($key, $today->subDays(6), $today, 'vs previous 7 days'),
            'last_30'    => self::rolling($key, $today->subDays(29), $today, 'vs previous 30 days'),
            default      => self::month($key, $today, $today),
        };
    }

    /**
     * A calendar month, ending today when the month is the current one.
     *
     * Ending on today rather than on the last of the month matters: a closing
     * figure that reaches past the last thing that happened is not a smaller
     * number, it is a wrong one. This is the same call HasQuickDateRanges
     * makes for `this_month`, for the same reason.
     */
    private static function month(string $key, CarbonImmutable $anchor, CarbonImmutable $today): self
    {
        $start = $anchor->startOfMonth();
        $end   = $anchor->isSameMonth($today) ? $today : $anchor->endOfMonth();

        $daysElapsed = self::inclusiveDays($start, $end);

        // The matching stretch of the previous month. Clamped, because there
        // is no 31st of February and asking for one silently rolls into March.
        $priorStart = $start->subMonthNoOverflow();
        $priorEnd   = $priorStart->addDays($daysElapsed - 1);
        $monthEnd   = $priorStart->endOfMonth();

        if ($priorEnd->greaterThan($monthEnd)) {
            $priorEnd = $monthEnd;
        }

        return new self(
            key:          $key,
            label:        self::options()[$key],
            start:        $start,
            end:          $end,
            priorStart:   $priorStart,
            priorEnd:     $priorEnd,
            priorLabel:   $key === 'this_month' ? 'vs same days last month' : 'vs previous month',
            daysElapsed:  $daysElapsed,
            daysInPeriod: $anchor->daysInMonth,
        );
    }

    /**
     * Whole days from start to end, counting both ends.
     *
     * Carbon 3 returns a float from diffInDays, and endOfMonth() lands at
     * 23:59:59.999 — so the naive `diffInDays() + 1` produced 31.999999999988
     * and was then implicitly truncated to an int by the constructor. It
     * happened to land on the right number, which is the worst kind of
     * correct: a promoted property away from silently reporting a 30-day month
     * as 29 days elapsed, and every run-rate projection with it.
     *
     * Both ends are flattened to midnight so the subtraction is between two
     * dates, which is what the question actually is.
     */
    private static function inclusiveDays(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) round($start->startOfDay()->diffInDays($end->startOfDay())) + 1;
    }

    private static function rolling(string $key, CarbonImmutable $start, CarbonImmutable $end, string $priorLabel): self
    {
        $days = self::inclusiveDays($start, $end);

        return new self(
            key:          $key,
            label:        self::options()[$key],
            start:        $start,
            end:          $end,
            priorStart:   $start->subDays($days),
            priorEnd:     $start->subDay(),
            priorLabel:   $priorLabel,
            daysElapsed:  $days,
            daysInPeriod: $days,
        );
    }

    /** A month still running, so month-to-date framing and a run rate apply. */
    public function isPartial(): bool
    {
        return $this->daysElapsed < $this->daysInPeriod;
    }

    /**
     * Whether today falls inside the window.
     *
     * Decides if the "Today" tiles belong on the page at all. Showing today's
     * takings beside a filter reading "Last month" is two different questions
     * answered in one row, and the reader has no way to tell which figure
     * belongs to which.
     */
    public function includesToday(): bool
    {
        return $this->end->isToday();
    }

    /**
     * Whether COGS for this window can be stock-adjusted.
     *
     * CostSummaryService folds stock takes into cost only in monthly mode; a
     * custom range skips them, so cost over a rolling window is purchase-based
     * and not comparable to a month's. The gauges say so rather than presenting
     * the two as the same measurement.
     */
    public function usesMonthlyCosting(): bool
    {
        return in_array($this->key, ['this_month', 'last_month'], true);
    }

    /** 'Y-m' for CostSummaryService's monthly mode. */
    public function costPeriod(): string
    {
        return $this->start->format('Y-m');
    }

    /** The extra [start, end] CostSummaryService needs for a rolling window. */
    public function costRange(): array
    {
        return $this->usesMonthlyCosting()
            ? [null, null]
            : [$this->start->toDateString(), $this->end->toDateString()];
    }

    /**
     * Project a month-to-date figure onto the whole month.
     *
     * Straight-line only, and deliberately so — anything cleverer implies a
     * model of the business the dashboard does not have. Null when the period
     * is complete, because a projection of a finished month is just the month.
     */
    public function runRate(float $value): ?float
    {
        if (! $this->isPartial() || $this->daysElapsed < 1) {
            return null;
        }

        return $value / $this->daysElapsed * $this->daysInPeriod;
    }

    /** "Day 12 of 31" / "1–31 Jul" — the line under the page title. */
    public function caption(): string
    {
        if ($this->isPartial()) {
            return "Day {$this->daysElapsed} of {$this->daysInPeriod} · "
                . $this->start->format('j M') . ' – ' . $this->end->format('j M Y');
        }

        return $this->start->format('j M') . ' – ' . $this->end->format('j M Y');
    }
}
