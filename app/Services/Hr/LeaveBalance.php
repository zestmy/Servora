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
 */
class LeaveBalance
{
    /**
     * @return Collection<int, array{type: LeaveType, entitled: float, taken: float, pending: float, remaining: float, blocked: ?string}>
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

        return $types->map(function (LeaveType $type) use ($entitlements, $used) {
            $entitlement = $entitlements[$type->id] ?? null;

            // No entitlement row means the type's default, not zero — a new
            // employee should not read as having no annual leave at all until
            // someone remembers to grant it.
            $entitled = $entitlement
                ? $entitlement->totalDays()
                : (float) $type->default_days;

            $rows     = $used[$type->id] ?? collect();
            $approved = round((float) $rows->where('status', LeaveRequest::APPROVED)->sum('days'), 1);
            $pending  = round((float) $rows->where('status', LeaveRequest::PENDING)->sum('days'), 1);

            return [
                'type'      => $type,
                'entitled'  => round($entitled, 1),
                'taken'     => $approved,
                'pending'   => $pending,
                'remaining' => round($entitled - $approved - $pending, 1),
                'granted'   => $entitlement !== null,
                'blocked'   => $type->blockedReason(),
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
