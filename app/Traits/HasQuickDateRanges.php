<?php

namespace App\Traits;

use Carbon\Carbon;

/**
 * The named date ranges that sit above a filtered list.
 *
 * Extracted at the third copy. Audit had one, Stock Management grew one, and
 * Purchasing was about to — three implementations of "last week" is three
 * chances for one of them to mean a different seven days than the others, on
 * screens people compare against each other.
 *
 * The component keeps `$dateFrom` and `$dateTo` and does its own querying; this
 * only decides what a name means. `$quickRange` empty means the dates were typed
 * by hand, which is why every screen using this clears it in updatedDateFrom().
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

    /** @return array<string, string> range key => the words on the badge */
    public static function quickRangeOptions(): array
    {
        return [
            'today'      => 'Today',
            'last_7'     => 'Last 7 days',
            'last_week'  => 'Last week',
            'last_30'    => 'Last 30 days',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'all'        => 'All time',
        ];
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
            'last_7'     => [$today->copy()->subDays(6), $today],
            /*
             * The calendar week just gone, Monday to Sunday — not the rolling
             * seven days above it. From a Wednesday the two overlap for most of
             * their length and disagree only at the start of the week, which is
             * exactly where a roster argument happens.
             *
             * Both days are named explicitly rather than left to the locale's
             * idea of where a week begins.
             */
            'last_week'  => [
                $today->copy()->subWeek()->startOfWeek(Carbon::MONDAY),
                $today->copy()->subWeek()->endOfWeek(Carbon::SUNDAY),
            ],
            'last_30'    => [$today->copy()->subDays(29), $today],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            default      => [null, null],   // 'all'
        };

        $this->dateFrom = $from?->toDateString() ?? '';
        $this->dateTo   = $to?->toDateString() ?? '';
    }

    /** What the current range is, in words, for a stat card or a caption. */
    protected function rangeLabel(): string
    {
        return match ($this->quickRange) {
            'today'      => 'today',
            'last_7'     => 'in the last 7 days',
            'last_week'  => 'last week',
            'last_30'    => 'in the last 30 days',
            'this_month' => 'this month',
            'last_month' => 'last month',
            'all'        => 'all time',
            default      => $this->dateFrom !== '' || $this->dateTo !== ''
                ? 'in the selected range'
                : 'all time',
        };
    }
}
