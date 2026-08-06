<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Scopes\CompanyScope;
use Illuminate\Support\Collection;

/**
 * What each employee has left, per leave type, for a year.
 *
 * TAKEN counts PENDING as well as approved. Someone with two days left who
 * applies for two and then applies for two more has to be stopped at the
 * second application — by the time an approver looks, they may already have
 * booked a flight.
 *
 * ONE TYPE DOES NOT WORK THIS WAY. The replacement public holiday balance is
 * not a yearly figure minus what was taken; it is a count of the holidays the
 * employee earned a day back for, and it is derived from the holiday register
 * rather than from an entitlement row.
 * @see \App\Services\Hr\ReplacementHolidays
 */
class LeaveBalance
{
    /**
     * @return Collection<int, array{type: LeaveType, entitled: float, taken: float, pending: float, expired: float, remaining: float, blocked: ?string}>
     */
    public function forEmployee(Employee $employee, int $year): Collection
    {
        $types = LeaveType::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $employee->company_id)
            ->active()
            ->ordered()
            ->get();

        $entitlements = LeaveEntitlement::withoutGlobalScope(CompanyScope::class)
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->get()
            ->keyBy('leave_type_id');

        $used = LeaveRequest::withoutGlobalScope(CompanyScope::class)
            ->where('employee_id', $employee->id)
            ->committing()
            ->whereYear('start_date', $year)
            ->get()
            ->groupBy('leave_type_id');

        $replacements = app(ReplacementHolidays::class);

        // Per-type now, so there is no company-wide row to read alongside it.
        $rules = app(LeaveRules::class);

        return $types->map(function (LeaveType $type) use ($entitlements, $used, $employee, $year, $replacements, $rules) {
            // The replacement type's balance comes from the holiday register,
            // not from an entitlement row — its `default_days` is meaningless
            // and an entitlement granted against it would be ignored, so the
            // grant form hides it rather than letting someone set a figure
            // that has no effect.
            if ($type->is_replacement_holiday) {
                return array_merge($replacements->summary($employee, $year), [
                    'type'    => $type,
                    'granted' => true,
                    'blocked' => $type->blockedReason() ?? $replacements->blockedReason($employee),
                ]);
            }

            $entitlement = $entitlements[$type->id] ?? null;

            // No entitlement row means the type's default, not zero — a new
            // employee should not read as having no annual leave at all until
            // someone remembers to grant it.
            $entitled = $entitlement
                ? $entitlement->totalDays()
                : (float) $type->default_days;

            /*
             * Pro-rated after the entitlement is resolved, so it applies to an
             * explicitly granted figure as well as to the type's default —
             * granting somebody 12 days should mean 12 days' worth of accrual,
             * not an escape from the rule.
             *
             * A no-op unless the type pro-rates AND the person joined or left
             * mid-year.
             */
            $entitled = $rules->entitlementFor($employee, $type, $entitled, $year);

            $rows     = $used[$type->id] ?? collect();
            $approved = round((float) $rows->where('status', LeaveRequest::APPROVED)->sum('days'), 1);
            $pending  = round((float) $rows->where('status', LeaveRequest::PENDING)->sum('days'), 1);

            return [
                'type'      => $type,
                'entitled'  => round($entitled, 1),
                'taken'     => $approved,
                'pending'   => $pending,
                'expired'   => 0.0,
                'remaining' => round($entitled - $approved - $pending, 1),
                'granted'   => $entitlement !== null,
                // The type's own reasons first — inactive, not claimable —
                // then the employee-specific one. Same order the replacement
                // holiday branch above uses.
                'blocked'   => $type->blockedReason()
                    ?? $rules->blockedReason($employee, $type),
            ];
        });
    }

    /** One type's remaining days, for validating an application. */
    public function remainingFor(Employee $employee, LeaveType $type, int $year): float
    {
        $row = $this->forEmployee($employee, $year)->firstWhere('type.id', $type->id);

        return $row ? (float) $row['remaining'] : 0.0;
    }
}
