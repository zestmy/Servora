<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The WIP review's choices — weekly or monthly, which period, how long a
 * trend — normalised in ONE place, for the screen and for its PDF.
 *
 * The PDF reads them from a query string anybody can edit. Two copies of
 * "snap to Monday, never past the current week, only offered trend lengths"
 * would sooner or later print a report the screen never showed.
 */
class WipReviewParameters
{
    public static function mode(?string $mode): string
    {
        return $mode === WeeklyWipReview::MONTH ? WeeklyWipReview::MONTH : WeeklyWipReview::WEEK;
    }

    /** Any date → the Monday of its week, never later than the current week. */
    public static function week(?string $date): Carbon
    {
        if (! $date) {
            return self::lastCompleteWeek();
        }

        try {
            $monday = Carbon::parse($date)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        } catch (\Throwable) {
            return self::lastCompleteWeek();
        }

        $latest = self::latest(WeeklyWipReview::WEEK);

        return $monday->gt($latest) ? $latest : $monday;
    }

    /** 'Y-m' or any date → the 1st of its month, never later than this month. */
    public static function month(?string $value): Carbon
    {
        if (! $value) {
            return self::lastCompleteMonth();
        }

        try {
            $first = (preg_match('/^\d{4}-\d{2}$/', $value) ? Carbon::createFromFormat('!Y-m', $value) : Carbon::parse($value))
                ->startOfMonth()->startOfDay();
        } catch (\Throwable) {
            return self::lastCompleteMonth();
        }

        $latest = self::latest(WeeklyWipReview::MONTH);

        return $first->gt($latest) ? $latest : $first;
    }

    public static function weeks($n): int
    {
        return in_array((int) $n, WeeklyWipReview::WEEK_OPTIONS, true) ? (int) $n : 8;
    }

    public static function months($n): int
    {
        return in_array((int) $n, WeeklyWipReview::MONTH_OPTIONS, true) ? (int) $n : 3;
    }

    /** The latest period that may be reviewed: the one in progress. */
    public static function latest(string $mode): Carbon
    {
        return $mode === WeeklyWipReview::MONTH
            ? now()->startOfMonth()->startOfDay()
            : now()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
    }

    public static function lastCompleteWeek(): Carbon
    {
        return now()->startOfWeek(CarbonInterface::MONDAY)->subWeek()->startOfDay();
    }

    public static function lastCompleteMonth(): Carbon
    {
        return now()->startOfMonth()->subMonthNoOverflow()->startOfDay();
    }
}
