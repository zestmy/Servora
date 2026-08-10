<?php

namespace App\Traits;

use Carbon\Carbon;

/**
 * The named date ranges that sit above a filtered list.
 *
 * Extracted at the third copy and grown at the fourth. Audit had one, Stock
 * Management grew one, Purchasing was about to, and Sales already had its own
 * with eleven ranges — including a "last week" that used startOfWeek() without
 * naming the day, so it depended on whatever Carbon's locale happened to say.
 * Four implementations of "last week" is four chances for one of them to mean a
 * different seven days than the others, on screens people compare.
 *
 * The split that makes one definition survive four screens: this trait owns what
 * every name MEANS, and each screen chooses which names it OFFERS. Sales wants
 * "yesterday" and "this year"; a stock list does not, and a nine-pill row is a
 * row you read instead of scan.
 *
 * The component keeps `$dateFrom` and `$dateTo` and does its own querying.
 */
trait HasQuickDateRanges
{
    /**
     * The named range currently applied.
     *
     * Empty means either "the dates were typed by hand" or "nothing chosen
     * yet" — mount() tells them apart by whether the dates are empty too. A
     * trait cannot carry a default a component is allowed to disagree with,
     * and Audit does disagree: it opens on the last 7 days, not 30.
     */
    public string $quickRange = '';

    /** Overridable: what this screen means by "recently". */
    protected function defaultQuickRange(): string
    {
        return 'last_30';
    }

    /** Apply the screen's default unless a range or explicit dates are set. */
    protected function bootQuickRange(): void
    {
        if ($this->dateFrom === '' && $this->dateTo === '') {
            $this->quickRange = $this->quickRange ?: $this->defaultQuickRange();
            $this->applyQuickRange($this->quickRange);
        }
    }

    /**
     * Every name this trait knows, and the words for it.
     *
     * One label per name as well as one meaning: "Last 7 days" on one screen and
     * "Last 7 Days" on another is the same drift in miniature.
     *
     * @return array<string, string>
     */
    public static function allQuickRanges(): array
    {
        return [
            'today'      => 'Today',
            'yesterday'  => 'Yesterday',
            'last_7'     => 'Last 7 days',
            'this_week'  => 'This week',
            'last_week'  => 'Last week',
            'last_30'    => 'Last 30 days',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'this_year'  => 'This year',
            'last_year'  => 'Last year',
            'all'        => 'All time',
        ];
    }

    /**
     * Which of them this screen puts on the row. Override to widen or narrow.
     *
     * @return array<int, string>
     */
    protected static function offeredQuickRanges(): array
    {
        return ['today', 'last_7', 'last_week', 'last_30', 'this_month', 'last_month', 'all'];
    }

    /** @return array<string, string> range key => the words on the badge */
    public static function quickRangeOptions(): array
    {
        return array_replace(
            array_flip(static::offeredQuickRanges()),
            array_intersect_key(static::allQuickRanges(), array_flip(static::offeredQuickRanges()))
        );
    }

    public function setQuickRange(string $range): void
    {
        $this->quickRange = $range;
        $this->applyQuickRange($range);

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    protected function applyQuickRange(string $range): void
    {
        $today = Carbon::today();

        [$from, $to] = match ($range) {
            'today'      => [$today, $today],
            'yesterday'  => [$today->copy()->subDay(), $today->copy()->subDay()],
            'last_7'     => [$today->copy()->subDays(6), $today],
            /*
             * Monday to Sunday, both named explicitly rather than left to the
             * locale's idea of where a week begins. The calendar week, not the
             * rolling seven days above it: from a Wednesday the two overlap for
             * most of their length and disagree only at the start of the week,
             * which is exactly where a roster argument happens.
             */
            'this_week'  => [$today->copy()->startOfWeek(Carbon::MONDAY), $today],
            'last_week'  => [
                $today->copy()->subWeek()->startOfWeek(Carbon::MONDAY),
                $today->copy()->subWeek()->endOfWeek(Carbon::SUNDAY),
            ],
            'last_30'    => [$today->copy()->subDays(29), $today],
            // To TODAY, not to the end of the month — same as this_week and
            // this_year above and below it. "This month" means the month so
            // far; ending it on a future date made reports that compute a
            // closing balance reach past the last thing that happened.
            'this_month' => [$today->copy()->startOfMonth(), $today],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_year'  => [$today->copy()->startOfYear(), $today],
            'last_year'  => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
            default      => [null, null],   // 'all'
        };

        $this->dateFrom = $from?->toDateString() ?? '';
        $this->dateTo   = $to?->toDateString() ?? '';
    }

    /** What the current range is, in words, for a stat card or a caption. */
    protected function rangeLabel(): string
    {
        $named = static::allQuickRanges()[$this->quickRange] ?? null;

        if ($named === null) {
            return $this->dateFrom !== '' || $this->dateTo !== ''
                ? 'in the selected range'
                : 'all time';
        }

        return match ($this->quickRange) {
            'today'     => 'today',
            'yesterday' => 'yesterday',
            'all'       => 'all time',
            'last_7'    => 'in the last 7 days',
            'last_30'   => 'in the last 30 days',
            default     => mb_strtolower($named),
        };
    }
}
