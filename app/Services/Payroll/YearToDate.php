<?php

namespace App\Services\Payroll;

use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * What each employee has already been paid this year, from committed payroll.
 *
 * The real LHDN MTD formula needs year-to-date remuneration, EPF and tax
 * already deducted; without them the only honest option is to annualise the
 * current month, which is what this system did until payroll runs existed.
 *
 * COMMITTED means approved or paid. A draft is a working figure that can still
 * be regenerated, and letting one feed the next month's tax would make PCB
 * depend on whether somebody happened to leave last month's run open.
 */
class YearToDate
{
    /** Zero for anyone with no committed history — January's correct answer. */
    public const NONE = [
        'gross' => 0.0, 'epf' => 0.0, 'pcb' => 0.0, 'zakat' => 0.0, 'months' => 0,
    ];

    /**
     * Totals for every employee, for the months of $month's year BEFORE it.
     *
     * @param  array<int, int>  $employeeIds
     * @return Collection<int, array{gross: float, epf: float, pcb: float, zakat: float, months: int}>
     */
    public function forMonth(int $companyId, array $employeeIds, Carbon $month): Collection
    {
        if (! $employeeIds) {
            return collect();
        }

        $yearStart = $month->copy()->startOfYear();

        $runIds = PayrollRun::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', [PayrollRun::APPROVED, PayrollRun::PAID])
            ->whereDate('period_month', '>=', $yearStart)
            // Strictly before the month being computed: the current month is
            // what we are working out, not part of its own history.
            ->whereDate('period_month', '<', $month->copy()->startOfMonth())
            ->pluck('id');

        if ($runIds->isEmpty()) {
            return collect();
        }

        return PayrollRunLine::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereIn('payroll_run_id', $runIds)
            ->whereIn('employee_id', $employeeIds)
            ->selectRaw('employee_id,
                         SUM(gross) as gross_total,
                         SUM(epf_employee) as epf_total,
                         SUM(pcb) as pcb_total,
                         COUNT(DISTINCT payroll_run_id) as month_count')
            ->groupBy('employee_id')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->employee_id => [
                'gross'  => (float) $r->gross_total,
                'epf'    => (float) $r->epf_total,
                'pcb'    => (float) $r->pcb_total,
                // Zakat is not on the line — it is a standing figure on the
                // statutory profile, so the calculator derives the accumulated
                // amount from that and the month count.
                'zakat'  => 0.0,
                'months' => (int) $r->month_count,
            ]]);
    }

    /**
     * Months left in the year AFTER this one — the MTD formula's `n`.
     *
     * December is 0, which makes the divisor (n + 1) exactly 1: the last
     * month's MTD settles whatever the year still owes.
     */
    public static function remainingMonths(Carbon $month): int
    {
        return 12 - (int) $month->format('n');
    }
}
