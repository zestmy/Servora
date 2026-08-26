<?php

namespace App\Services\Hr;

use App\Models\CompensationSetting;
use App\Models\Employee;
use App\Models\OvertimeClaim;
use App\Models\TimeOffAllocation;
use App\Models\TimeOffRequest;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;

/**
 * How many overtime hours an employee may still take as time off, and the
 * allocation that spends them.
 *
 * THE RULE: an hour of overtime is either PAID or TAKEN OFF, never both, and
 * the claim's own `settlement` says which. Only claims APPROVED, UNPAID and
 * SETTLED AS TIME OFF contribute — a pending claim is not yet owed, a paid one
 * is already settled in cash, and a payroll-destined one is going to be.
 *
 * That last clause is a change from how this started. It counted every
 * approved unpaid claim, on the reasoning that a payroll-destined one stayed
 * available until a run stamped paid_at on it. The effect was to make the
 * settlement flag advisory — overtime explicitly marked to be PAID could be
 * taken as leave, so "what is this person owed" had two answers until payday
 * picked one. The column exists to decide that, so the balance reads it.
 *
 * Allocation is OLDEST CLAIM FIRST. Overtime nearest to being paid is used
 * up first, so a request does not quietly reserve recent hours and leave old
 * ones to be paid out from under it.
 */
class TimeOffBalance
{
    /**
     * Hours available to request, after everything already committed.
     *
     * Pending requests count against it as well as approved ones: someone with
     * four hours who applies for four and then applies for four more has to be
     * stopped at the second application, not at the second approval.
     */
    public function availableHours(Employee $employee): float
    {
        return round(max(0.0, $this->earnedHours($employee) - $this->committedHours($employee)), 2);
    }

    /** Approved, unpaid overtime, less anything already taken off it. */
    public function earnedHours(Employee $employee): float
    {
        return round((float) $this->unpaidClaims($employee)
            ->sum(DB::raw('total_ot_hours - hours_taken_off')), 2);
    }

    /**
     * Hours held by requests that have not been settled — the pending ones.
     *
     * Approved requests are NOT counted here: approving one writes its hours
     * onto the claims through `hours_taken_off`, so they have already left
     * earnedHours() and counting them again would halve the balance.
     */
    public function committedHours(Employee $employee): float
    {
        return round((float) TimeOffRequest::withoutGlobalScope(CompanyScope::class)
            ->where('employee_id', $employee->id)
            ->where('status', TimeOffRequest::PENDING)
            ->sum('hours'), 2);
    }

    /**
     * Overtime approved AS TIME OFF that the company still owes, oldest first.
     *
     * Both halves of the settlement test earn their place:
     *
     * `status = approved` keeps out claims still waiting on an approver.
     * Hours nobody has agreed to yet are not a balance, and offering them as
     * one invites somebody to book leave against overtime that is then
     * rejected.
     *
     * `settlement = time_off` is the newer half. This used to count every
     * approved unpaid claim, payroll-destined ones included, on the reasoning
     * that they stayed available until a payroll run stamped paid_at on them.
     * That made the settlement flag advisory: a claim explicitly marked to be
     * PAID could be taken as leave instead, and the two answers to "what is
     * this person owed" disagreed until payday resolved it. The column exists
     * to say which half is money and which is leave, so the leave balance
     * reads it.
     *
     * Nothing else changes shape. release() works off the allocation rows
     * rather than this query, so hours already taken against a payroll claim
     * stay taken and come back correctly if the request is cancelled.
     */
    public function unpaidClaims(Employee $employee)
    {
        return OvertimeClaim::withoutGlobalScope(CompanyScope::class)
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('settlement', OvertimeClaim::SETTLE_TIME_OFF)
            ->whereNull('paid_at')
            ->whereRaw('total_ot_hours > hours_taken_off')
            ->orderBy('claim_date')
            ->orderBy('id');
    }

    /**
     * Spend a request's hours against the oldest unpaid claims.
     *
     * Called on APPROVAL, not on application — an application is a request,
     * and reserving hours against claims that might then be paid out would
     * leave the ledger describing something that did not happen.
     *
     * @throws \RuntimeException when the balance no longer covers it
     */
    public function allocate(TimeOffRequest $request): void
    {
        $employee = $request->employee;
        $needed   = round((float) $request->hours, 2);

        DB::transaction(function () use ($request, $employee, $needed) {
            $remaining = $needed;

            // Locked for update: two approvers acting at once must not both
            // see the same hours as available and spend them twice.
            $claims = $this->unpaidClaims($employee)->lockForUpdate()->get();

            $available = round($claims->sum(fn ($c) => (float) $c->total_ot_hours - (float) $c->hours_taken_off), 2);

            if ($available + 0.001 < $needed) {
                throw new \RuntimeException(
                    'Only ' . rtrim(rtrim(number_format($available, 2), '0'), '.')
                    . ' unpaid overtime hour(s) are available — this request needs '
                    . rtrim(rtrim(number_format($needed, 2), '0'), '.') . '.'
                );
            }

            foreach ($claims as $claim) {
                if ($remaining <= 0.001) {
                    break;
                }

                $spare = round((float) $claim->total_ot_hours - (float) $claim->hours_taken_off, 2);
                $take  = min($spare, $remaining);

                if ($take <= 0) {
                    continue;
                }

                TimeOffAllocation::updateOrCreate(
                    ['time_off_request_id' => $request->id, 'overtime_claim_id' => $claim->id],
                    ['hours' => $take],
                );

                $claim->hours_taken_off = round((float) $claim->hours_taken_off + $take, 2);
                $claim->save();

                $remaining = round($remaining - $take, 2);
            }
        });
    }

    /**
     * Give the hours back — used when an approved request is cancelled.
     *
     * The allocations are deleted rather than kept as a reversal, because the
     * claim's own `hours_taken_off` is the figure payroll reads and a pair of
     * offsetting rows would leave it needing to net them out.
     */
    public function release(TimeOffRequest $request): void
    {
        DB::transaction(function () use ($request) {
            foreach ($request->allocations()->with('claim')->get() as $allocation) {
                if ($claim = $allocation->claim) {
                    $claim->hours_taken_off = round(
                        max(0, (float) $claim->hours_taken_off - (float) $allocation->hours), 2
                    );
                    $claim->save();
                }

                $allocation->delete();
            }
        });
    }

    /**
     * The employee's contract working day, for turning hours into days.
     *
     * Their own figure if they have one, otherwise the company default —
     * a per-person copy of the same number is a second thing to keep in step.
     */
    public function workingDayHours(Employee $employee): float
    {
        if ($employee->daily_working_hours !== null) {
            return (float) $employee->daily_working_hours;
        }

        return (float) CompensationSetting::forCompany($employee->company_id)->daily_working_hours ?: 8.0;
    }

    /** "1.5 days" for a set of hours, using this employee's own working day. */
    public function asDays(Employee $employee, float $hours): float
    {
        $perDay = $this->workingDayHours($employee);

        return $perDay > 0 ? round($hours / $perDay, 2) : 0.0;
    }
}
