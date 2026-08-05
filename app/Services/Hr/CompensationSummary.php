<?php

namespace App\Services\Hr;

use App\Models\CompensationSetting;
use App\Models\Employee;
use App\Models\EmployeePayComponent;
use App\Models\OvertimeClaim;
use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What each employee is owed for a month: basic, plus the allowances and
 * deductions in force that month, plus approved overtime.
 *
 * Everything is computed FOR THE PERIOD rather than from today's values —
 * an allowance that ended in February must not appear in March, and a salary
 * revision that lands in April must not rewrite March. That is the whole
 * reason employee_pay_components carries dates.
 *
 * NOT INCLUDED: service charge. It is distributed from the attendance grid by
 * ServiceChargePeriod::distribute(), which needs the full month of attendance
 * codes per employee as its input; reproducing that here would mean a second
 * implementation of the same rules, and two service charge figures that can
 * disagree is worse than one figure in one place. The Attendance Record screen
 * remains where service charge is read.
 */
class CompensationSummary
{
    /**
     * @param  Builder  $employees  already scoped to the outlets wanted
     * @return array{
     *     rows: Collection,
     *     totals: array<string, float>,
     *     from: Carbon, to: Carbon,
     *     settings: CompensationSetting,
     * }
     */
    public function forMonth(Builder $employees, int $companyId, Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to   = $month->copy()->endOfMonth();

        $settings = CompensationSetting::forCompany($companyId);

        // Staff who were employed for any part of the month: a leaver is still
        // owed for the days they worked, which is the point of the period.
        $staff = (clone $employees)
            ->employedDuring($from->toDateString())
            ->with('outlet:id,name', 'section:id,name')
            ->orderBy('name')
            ->get();

        $assignments = EmployeePayComponent::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $staff->pluck('id'))
            ->overlapping($from->toDateString(), $to->toDateString())
            ->with('component')
            ->get()
            ->groupBy('employee_id');

        // Approved claims only: a pending claim is not yet an amount owed.
        $otHours = OvertimeClaim::withoutGlobalScope(CompanyScope::class)
            ->whereIn('employee_id', $staff->pluck('id'))
            ->where('status', 'approved')
            ->whereBetween('claim_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('employee_id, ot_type, SUM(total_ot_hours) as hours')
            ->groupBy('employee_id', 'ot_type')
            ->get()
            ->groupBy('employee_id');

        $rows = $staff->map(function (Employee $employee) use ($assignments, $otHours, $settings) {
            $basic = $employee->basic_salary !== null ? (float) $employee->basic_salary : 0.0;

            $components = ($assignments[$employee->id] ?? collect())
                ->filter(fn ($a) => $a->component !== null)
                ->map(fn ($a) => [
                    'name'   => $a->component->name,
                    'kind'   => $a->component->kind,
                    'amount' => $a->component->resolveAmount((float) $a->amount, $basic),
                ])
                ->values();

            $allowances = round($components->where('kind', 'allowance')->sum('amount'), 2);
            $deductions = round(abs($components->where('kind', 'deduction')->sum('amount')), 2);

            $rate    = $settings->hourlyRate($employee->basic_salary !== null ? (float) $employee->basic_salary : null, $employee->pay_type);
            $otRows  = $otHours[$employee->id] ?? collect();
            $otTotal = 0.0;
            $otByType = [];

            foreach ($otRows as $row) {
                $hours = round((float) $row->hours, 2);
                $amount = $rate !== null ? round($hours * $rate * $settings->multiplierFor($row->ot_type), 2) : null;
                $otByType[$row->ot_type] = ['hours' => $hours, 'amount' => $amount];
                $otTotal += $amount ?? 0;
            }

            return [
                'employee_id' => $employee->id,
                'name'        => $employee->name,
                'staff_id'    => $employee->staff_id,
                'outlet'      => $employee->outlet?->name,
                'section'     => $employee->section?->name,
                'pay_type'    => $employee->pay_type,
                'basic'       => $basic,
                'hourly_rate' => $rate,
                'components'  => $components,
                'allowances'  => $allowances,
                'deductions'  => $deductions,
                'ot_hours'    => round(collect($otByType)->sum('hours'), 2),
                'ot_by_type'  => $otByType,
                'ot_amount'   => round($otTotal, 2),
                // Salary is unknown, so the OT figure would be a guess. Flagged
                // rather than silently reported as zero.
                'ot_unrated'  => $rate === null && $otRows->isNotEmpty(),
                'gross'       => round($basic + $allowances + $otTotal - $deductions, 2),
            ];
        });

        return [
            'rows'   => $rows,
            'totals' => [
                'basic'      => round($rows->sum('basic'), 2),
                'allowances' => round($rows->sum('allowances'), 2),
                'deductions' => round($rows->sum('deductions'), 2),
                'ot_amount'  => round($rows->sum('ot_amount'), 2),
                'ot_hours'   => round($rows->sum('ot_hours'), 2),
                'gross'      => round($rows->sum('gross'), 2),
            ],
            'from'     => $from,
            'to'       => $to,
            'settings' => $settings,
        ];
    }
}
