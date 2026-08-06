<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\LeaveSetting;
use App\Models\LeaveType;
use Carbon\Carbon;

/**
 * The two company-wide rules for annual leave: pro-rating, and whether it may
 * be taken during probation.
 *
 * One object, read by the balance screen and by BOTH application paths — the
 * staff portal and the HR screen. Those three have to agree: a balance that
 * shows 8 days against a form that refuses the application is worse than
 * either rule on its own, because the employee cannot tell which one is lying.
 *
 * Both rules apply ONLY to types flagged is_annual. Medical leave is not
 * pro-rated in the joining year and maternity certainly is not, so a blanket
 * rule across every type would be wrong in a way that costs somebody actual
 * days off.
 */
class AnnualLeaveRules
{
    /**
     * Entitlement after pro-rating, if the company pro-rates.
     *
     * MALAYSIAN PRACTICE, and the arithmetic is deliberately conservative:
     * completed months of service WITHIN the year, over twelve, rounded DOWN
     * to the nearest half day. Rounding down rather than to nearest means the
     * figure never grants a day that has not been earned yet — the direction
     * that is defensible to an employee is the one that under-promises, since
     * leave already taken cannot be un-taken when the number is corrected.
     *
     * Bounded at BOTH ends. Somebody who joined in March gets March-to-December
     * and somebody who resigns in August gets January-to-August, because a
     * leaver's final entitlement is exactly the case where over-granting turns
     * into a payment nobody agreed to.
     *
     * A full year of service returns the entitlement untouched, so this is a
     * no-op for everybody except joiners and leavers.
     */
    public function entitlementFor(Employee $employee, LeaveType $type, float $entitled, int $year, ?LeaveSetting $settings = null): float
    {
        $settings ??= LeaveSetting::forCompany($employee->company_id);

        if (! $type->is_annual || ! $settings->annual_prorated || $entitled <= 0) {
            return $entitled;
        }

        $months = $this->servedMonthsIn($employee, $year);

        if ($months >= 12) {
            return $entitled;
        }

        if ($months <= 0) {
            return 0.0;
        }

        // Down to the nearest half day. floor() on doubled halves, so 4.9 -> 4.5.
        return floor($entitled * $months / 12 * 2) / 2;
    }

    /**
     * Completed months of service inside a given calendar year, 0..12.
     *
     * Counts a month as served only once it is COMPLETE, which is what makes
     * this an entitlement that accrues rather than one granted on arrival.
     * Somebody who joined on the 20th of March has not served March.
     */
    public function servedMonthsIn(Employee $employee, int $year): int
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();

        /*
         * The end is EXCLUSIVE — 1 January of the following year, not 31
         * December — and getting this wrong is subtle and expensive.
         *
         * diffInMonths counts whole months between two instants, so 1 Jan to
         * 31 Dec is eleven of them: the twelfth does not complete until 1
         * January comes round. Measured that way, pro-rating quietly took a
         * month off EVERY employee, including people who had been here for
         * years and should have been untouched by it. An exclusive end makes a
         * full calendar year read as the twelve months it is.
         *
         * A resignation is bumped by a day for the same reason: somebody
         * leaving on 31 August served August, and an inclusive date would
         * count only seven months for what is plainly eight.
         */
        $yearEnd = Carbon::create($year + 1, 1, 1)->startOfDay();

        $start = $employee->join_date
            ? Carbon::parse($employee->join_date)->startOfDay()->max($yearStart)
            : $yearStart;

        // A resignation dated inside the year closes the window early. Any
        // other status has no end date that matters here.
        $end = ($employee->employment_status === 'resigned' && $employee->employment_status_date)
            ? Carbon::parse($employee->employment_status_date)->startOfDay()->addDay()->min($yearEnd)
            : $yearEnd;

        if ($end->lte($start)) {
            return 0;
        }

        // Whole months only, which is what makes this accrue rather than be
        // granted on arrival: joining on the 20th does not earn that month.
        return (int) max(0, min(12, $start->diffInMonths($end)));
    }

    /**
     * Why this person may not take annual leave yet, or null.
     *
     * Probation and extended probation both count as unconfirmed. A part-timer,
     * an outsourced worker or somebody with no status set is NOT blocked —
     * "not confirmed" and "not on probation" are different things, and refusing
     * leave to everybody whose record is simply incomplete would be a
     * data-entry gap turning into a refused application.
     */
    public function blockedReason(Employee $employee, LeaveType $type, ?LeaveSetting $settings = null): ?string
    {
        if (! $type->is_annual) {
            return null;
        }

        $settings ??= LeaveSetting::forCompany($employee->company_id);

        if (! $settings->annual_requires_confirmation) {
            return null;
        }

        if (! in_array($employee->employment_status, ['probation', 'extended_probation'], true)) {
            return null;
        }

        $until = $employee->employment_status_date
            ? ' Probation runs to ' . Carbon::parse($employee->employment_status_date)->format('d M Y') . '.'
            : '';

        return 'Annual leave is available once your employment is confirmed.' . $until;
    }
}
