<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\LeaveApprover;
use App\Scopes\CompanyScope;
use Illuminate\Support\Collection;

/**
 * Who should be told about one employee's leave, and who may decide it.
 *
 * RESOLUTION ORDER, most specific first:
 *
 *   1. The employee's SUPERIOR (`reports_to_id`). What staff expect — "it
 *      goes to my manager" — and what a company already knows. A superior may
 *      have no login at all; they are still the right person to email, so this
 *      is included whether or not they can act on it in the app.
 *   2. Leave approvers scoped to the employee's OUTLET AND SECTION.
 *   3. Leave approvers scoped to the outlet, any section.
 *   4. Company-wide leave approvers (no outlet).
 *
 * Steps 2–4 are ADDITIVE rather than a stop-at-first-match: a company that
 * names both a section approver and an HR catch-all means both to be told,
 * and silently dropping one because a narrower rule matched is how an approval
 * ends up sitting unread. Deduplicated by email so nobody is told twice.
 *
 * An employee with no superior and no approver returns an EMPTY list, which
 * the caller records rather than treating as an error — a request nobody was
 * told about is a real state, and the screen says so.
 */
class LeaveApprovers
{
    /**
     * @return Collection<int, array{name: string, email: string, source: string, user_id: ?int}>
     */
    public function forEmployee(Employee $employee): Collection
    {
        $recipients = collect();

        // 1 — the reporting line.
        if ($superior = $this->superior($employee)) {
            if (filter_var((string) $superior->email, FILTER_VALIDATE_EMAIL)) {
                $recipients->push([
                    'name'    => $superior->name,
                    'email'   => trim($superior->email),
                    'source'  => 'superior',
                    'user_id' => null,
                ]);
            }
        }

        // 2–4 — named approvers, widening scope.
        $approvers = LeaveApprover::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $employee->company_id)
            ->with('user:id,name,email')
            ->where(function ($q) use ($employee) {
                $q->whereNull('outlet_id')
                  ->orWhere('outlet_id', $employee->outlet_id);
            })
            ->where(function ($q) use ($employee) {
                $q->whereNull('section_id')
                  ->orWhere('section_id', $employee->section_id);
            })
            ->get();

        foreach ($approvers as $approver) {
            $user = $approver->user;

            if (! $user || ! filter_var((string) $user->email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $recipients->push([
                'name'    => $user->name,
                'email'   => trim($user->email),
                'source'  => $approver->outlet_id === null ? 'company approver' : 'outlet approver',
                'user_id' => $user->id,
            ]);
        }

        // The superior wins a tie, being first in — so a manager who is also a
        // named approver is told once, as their superior.
        return $recipients->unique(fn ($r) => strtolower($r['email']))->values();
    }

    /**
     * The employee's superior, if the link is intact and points somewhere real.
     *
     * Guards against a self-reference, which would be a loop and is always a
     * data-entry slip rather than a policy.
     */
    public function superior(Employee $employee): ?Employee
    {
        if (! $employee->reports_to_id || $employee->reports_to_id === $employee->id) {
            return null;
        }

        return Employee::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $employee->company_id)
            ->find($employee->reports_to_id);
    }

    /**
     * Everyone who may be picked as a superior for this employee.
     *
     * Excludes the person themselves, and anyone who already reports to them —
     * a two-person cycle is the one that actually happens, and it makes the
     * chain unwalkable.
     */
    public function candidateSuperiors(Employee $employee): Collection
    {
        return Employee::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $employee->company_id)
            ->where('is_active', true)
            ->when($employee->exists, fn ($q) => $q
                ->where('id', '!=', $employee->id)
                ->where(fn ($w) => $w->whereNull('reports_to_id')
                    ->orWhere('reports_to_id', '!=', $employee->id)))
            ->orderBy('name')
            ->get();
    }
}
