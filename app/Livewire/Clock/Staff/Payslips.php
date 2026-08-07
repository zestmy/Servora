<?php

namespace App\Livewire\Clock\Staff;

use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Scopes\CompanyScope;

/**
 * The employee's own payslips.
 *
 * Same reasoning as the punch history one screen over: a figure someone can
 * look up is a question, and a figure they have to ask a manager for is a
 * grievance. Payslips were reaching staff as an emailed PDF or a printout at
 * the counter, both of which are gone by the time anybody wants to check last
 * March.
 *
 * ONLY APPROVED AND PAID RUNS. A draft is a working document — it is
 * regenerated whenever another OT claim is approved, and the net figure moves
 * while it is one. Somebody who reads a draft as their payslip has been told a
 * number that is not yet true, and will hold the company to it.
 */
class Payslips extends StaffComponent
{
    /**
     * Two years. Long enough for the tax filing anybody actually comes here
     * for, and short enough that the list is scrolled rather than searched.
     */
    private const MONTHS = 24;

    public function render()
    {
        $employee = $this->staff();

        /*
         * Keyed on the LINE, not the run. A run is per-outlet, and somebody who
         * transferred mid-year has lines under two of them; starting from the
         * employee's own lines gets both without having to know which runs to
         * look in.
         *
         * CompanyScope is dropped and company_id matched by hand — there is no
         * authenticated user on this app, so the scope resolves from the
         * subdomain at best and matches nothing at worst.
         */
        $lines = PayrollRunLine::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->whereHas('run', fn ($q) => $q
                ->withoutGlobalScope(CompanyScope::class)
                ->whereIn('status', [PayrollRun::APPROVED, PayrollRun::PAID])
                ->where('period_month', '>=', now()->subMonths(self::MONTHS)->startOfMonth()->toDateString()))
            ->with('run')
            ->get()
            // Newest first, and sorted here rather than in SQL: the ordering
            // key lives on the run, and a join for a list this short buys
            // nothing worth the query.
            ->sortByDesc(fn ($line) => $line->run?->period_month?->toDateString() ?? '')
            ->values();

        return view('livewire.clock.staff.payslips', [
            'lines' => $lines,
        ])->layout('layouts.clock-staff', $this->shell('My payslips'));
    }
}
