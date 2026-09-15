<?php

namespace App\Services\Reports;

use App\Models\CompensationSetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeClaim;
use App\Models\Outlet;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\PurchaseCapture;
use App\Models\PurchaseRecord;
use App\Models\SalesRecord;
use App\Models\StaffMealRecord;
use App\Models\WastageRecord;
use App\Services\PurchaseSupplierBreakdown;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The figures behind the WIP meeting: one reviewed period against the one
 * before, and a trend of the periods leading up to it — sales against
 * purchases (overall, by department and by outlet), wastage, staff meals,
 * stock transfers between outlets, overtime claims and, by month, labour cost
 * from payroll.
 *
 * TWO GRANULARITIES. Weeks run Monday to Sunday, labelled by ISO week number.
 * Months are calendar months. Labour cost is MONTHLY ONLY: payroll is run by
 * the month, and spreading it across weeks would invent figures nobody paid.
 *
 * PAY IS GATED. The report itself only needs reports.view; overtime COST and
 * labour cost are computed only when the caller says the viewer may see pay
 * (hr.compensation, see Employee::canViewPay), and are absent from the result
 * otherwise rather than merely hidden in the view.
 *
 * DEPARTMENTS AND SALES. A sale has no department of its own; its lines carry
 * a sales category, and a department points at the sales category it sells
 * through (Department::sales_category_id). Revenue follows that link. Revenue
 * whose category no department claims — or a sale keyed as a header total
 * with no lines — is reported as "Unassigned" rather than dropped, so the
 * department rows always add up to the sales total.
 *
 * Grouped by the raw date column and bucketed in PHP: YEARWEEK() and
 * DATE_FORMAT() are MySQL-only and would leave this untestable on SQLite.
 */
class WeeklyWipReview
{
    public const WEEK  = 'week';
    public const MONTH = 'month';

    /** 2 is the smallest that still has a week before the reviewed one. */
    public const WEEK_OPTIONS = [2, 4, 8, 12];

    /** A 1-month trend still compares with the month before; only the trend is cut. */
    public const MONTH_OPTIONS = [1, 2, 3, 6];

    private const UNASSIGNED = 'none';

    /**
     * @param  array<int, int>  $outletIds  empty = every outlet the company has
     */
    public function build(
        int $companyId,
        array $outletIds,
        Carbon $anchor,
        int $count = 8,
        string $granularity = self::WEEK,
        bool $includeDraftPayroll = false,
        bool $canViewPay = false,
    ): array {
        $monthly = $granularity === self::MONTH;
        $options = $monthly ? self::MONTH_OPTIONS : self::WEEK_OPTIONS;
        $count   = in_array($count, $options, true) ? $count : ($monthly ? 3 : 8);

        // Always at least two periods underneath, so the comparison exists
        // even when the trend asked for is a single month.
        $buckets = max($count, 2);

        $current = $monthly
            ? $anchor->copy()->startOfMonth()->startOfDay()
            : $anchor->copy()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $first = $monthly
            ? $current->copy()->subMonthsNoOverflow($buckets - 1)
            : $current->copy()->subWeeks($buckets - 1);

        $periods = [];
        for ($i = 0; $i < $buckets; $i++) {
            $start = $monthly ? $first->copy()->addMonthsNoOverflow($i) : $first->copy()->addWeeks($i);
            $end   = $monthly ? $start->copy()->endOfMonth() : $start->copy()->addDays(6);

            $periods[] = [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
                'label' => $monthly ? $start->format('M Y') : 'W' . $start->isoWeek() . ' · ' . $start->format('j M'),
                'range' => $monthly ? $start->format('F Y') : $start->format('j M') . ' – ' . $end->format('j M Y'),
            ];
        }

        $range = [$periods[0]['start'], $periods[$buckets - 1]['end'] . ' 23:59:59'];
        $index = array_flip(array_column($periods, 'start'));
        $cur   = $buckets - 1;
        $prev  = $buckets - 2;
        $zero  = array_fill(0, $buckets, 0.0);

        $bucket = function ($date) use ($index, $monthly): ?int {
            $d   = Carbon::parse($date);
            $key = ($monthly ? $d->startOfMonth() : $d->startOfWeek(CarbonInterface::MONDAY))->toDateString();

            return $index[$key] ?? null;
        };

        $outlets = fn ($query, string $column = 'outlet_id') => $outletIds ? $query->whereIn($column, $outletIds) : $query;

        $totals = array_fill_keys([
            'sales', 'purchases', 'wastage', 'staff_meal', 'transfers',
            'ot_hours', 'ot_cost', 'ot_pending_hours', 'labour_cost',
        ], $zero);
        $byDept    = [];
        $byOutlet  = [];
        $lineSales = $zero;

        $add = function (array &$target, $key, string $metric, int $w, float $amount) use ($zero): void {
            $target[$key][$metric] ??= $zero;
            $target[$key][$metric][$w] += $amount;
        };

        // ── Sales: header totals, for the period and the outlet ───────────
        $sales = $outlets(SalesRecord::withoutGlobalScopes()->where('company_id', $companyId)->whereNull('deleted_at'))
            ->whereBetween('sale_date', $range)
            ->selectRaw('sale_date as d, outlet_id, SUM(total_revenue) as amount')
            ->groupBy('sale_date', 'outlet_id')
            ->get();

        foreach ($sales as $row) {
            if (($w = $bucket($row->d)) === null) continue;
            $totals['sales'][$w] += (float) $row->amount;
            $add($byOutlet, (int) $row->outlet_id, 'sales', $w, (float) $row->amount);
        }

        // ── Sales by department, through each line's sales category ───────
        $departments = Department::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'sales_category_id']);

        // First department per category wins, so a category two departments
        // share is not counted twice.
        $deptForCategory = [];
        foreach ($departments as $d) {
            if ($d->sales_category_id && ! isset($deptForCategory[$d->sales_category_id])) {
                $deptForCategory[$d->sales_category_id] = $d->id;
            }
        }

        $categorySales = DB::table('sales_record_lines as l')
            ->join('sales_records as r', 'r.id', '=', 'l.sales_record_id')
            ->where('r.company_id', $companyId)
            ->whereNull('r.deleted_at')
            ->whereBetween('r.sale_date', $range)
            ->when($outletIds, fn ($q) => $q->whereIn('r.outlet_id', $outletIds))
            ->selectRaw('r.sale_date as d, l.sales_category_id as category_id, SUM(l.total_revenue) as amount')
            ->groupBy('r.sale_date', 'l.sales_category_id')
            ->get();

        foreach ($categorySales as $row) {
            if (($w = $bucket($row->d)) === null) continue;
            $key = $deptForCategory[$row->category_id] ?? self::UNASSIGNED;
            $add($byDept, $key, 'sales', $w, (float) $row->amount);
            $lineSales[$w] += (float) $row->amount;
        }

        // Header revenue with no lines behind it (a Z-report total, say).
        foreach ($totals['sales'] as $w => $amount) {
            $gap = $amount - $lineSales[$w];
            if ($gap > 0.005) {
                $add($byDept, self::UNASSIGNED, 'sales', $w, $gap);
            }
        }

        // ── Purchases ─────────────────────────────────────────────────────
        $purchases = $outlets(PurchaseRecord::withoutGlobalScopes()->where('company_id', $companyId)->whereNull('deleted_at'))
            ->whereBetween('purchase_date', $range)
            ->selectRaw('purchase_date as d, outlet_id, department_id, SUM(total_amount) as amount')
            ->groupBy('purchase_date', 'outlet_id', 'department_id')
            ->get();

        // PLUS the purchases keyed in by hand on Stock Management > Purchases,
        // which live in purchase_captures and not in purchase_records — the
        // latter only holds goods received against a PO. Reading records alone
        // left most companies showing no purchases at all. Blended the same
        // way CostSummaryService does, so this and the COGS report agree; the
        // two never overlap, as receiving a PO does not write a capture.
        $captures = $outlets(PurchaseCapture::withoutGlobalScopes()->where('company_id', $companyId)->whereNull('deleted_at'))
            ->whereBetween('purchase_date', $range)
            ->selectRaw('purchase_date as d, outlet_id, department_id, SUM(amount) as amount')
            ->groupBy('purchase_date', 'outlet_id', 'department_id')
            ->get();

        foreach ($purchases->concat($captures) as $row) {
            if (($w = $bucket($row->d)) === null) continue;
            $totals['purchases'][$w] += (float) $row->amount;
            $add($byDept, $row->department_id ?: self::UNASSIGNED, 'purchases', $w, (float) $row->amount);
            $add($byOutlet, (int) $row->outlet_id, 'purchases', $w, (float) $row->amount);
        }

        // ── Wastage ───────────────────────────────────────────────────────
        $wastage = $outlets(WastageRecord::withoutGlobalScopes()->where('company_id', $companyId)->whereNull('deleted_at'))
            ->whereBetween('wastage_date', $range)
            ->selectRaw('wastage_date as d, outlet_id, department_id, SUM(total_cost) as amount')
            ->groupBy('wastage_date', 'outlet_id', 'department_id')
            ->get();

        foreach ($wastage as $row) {
            if (($w = $bucket($row->d)) === null) continue;
            $totals['wastage'][$w] += (float) $row->amount;
            $add($byDept, $row->department_id ?: self::UNASSIGNED, 'wastage', $w, (float) $row->amount);
            $add($byOutlet, (int) $row->outlet_id, 'wastage', $w, (float) $row->amount);
        }

        // ── Staff meals — an outlet cost, with no department ──────────────
        $meals = $outlets(StaffMealRecord::withoutGlobalScopes()->where('company_id', $companyId)->whereNull('deleted_at'))
            ->whereBetween('meal_date', $range)
            ->selectRaw('meal_date as d, outlet_id, SUM(total_cost) as amount')
            ->groupBy('meal_date', 'outlet_id')
            ->get();

        foreach ($meals as $row) {
            if (($w = $bucket($row->d)) === null) continue;
            $totals['staff_meal'][$w] += (float) $row->amount;
            $add($byOutlet, (int) $row->outlet_id, 'staff_meal', $w, (float) $row->amount);
        }

        // ── Stock transfers between outlets ───────────────────────────────
        // Valued on their lines (quantity × unit cost) — a transfer carries no
        // total of its own; the same computation as the Transfers tab. A draft
        // has not moved anything and a cancelled one never will, so only
        // in-transit and received transfers count. Company-wide a transfer
        // nets to nothing, so each outlet sees what it sent and what it got.
        $transfers = DB::table('outlet_transfer_lines as l')
            ->join('outlet_transfers as t', 't.id', '=', 'l.outlet_transfer_id')
            ->where('t.company_id', $companyId)
            ->whereNull('t.deleted_at')
            ->whereIn('t.status', ['in_transit', 'received'])
            ->whereBetween('t.transfer_date', $range)
            ->when($outletIds, fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('t.from_outlet_id', $outletIds)
                ->orWhereIn('t.to_outlet_id', $outletIds)))
            ->selectRaw('t.transfer_date as d, t.from_outlet_id, t.to_outlet_id, SUM(l.quantity * l.unit_cost) as amount')
            ->groupBy('t.transfer_date', 't.from_outlet_id', 't.to_outlet_id')
            ->get();

        foreach ($transfers as $row) {
            if (($w = $bucket($row->d)) === null) continue;
            $amount = (float) $row->amount;
            $totals['transfers'][$w] += $amount;

            // Only the side inside the filter: with one outlet selected, the
            // outlet at the other end of a transfer is not in this report.
            if (! $outletIds || in_array((int) $row->from_outlet_id, $outletIds, true)) {
                $add($byOutlet, (int) $row->from_outlet_id, 'transfers_out', $w, $amount);
            }
            if (! $outletIds || in_array((int) $row->to_outlet_id, $outletIds, true)) {
                $add($byOutlet, (int) $row->to_outlet_id, 'transfers_in', $w, $amount);
            }
        }

        // ── Overtime claims ───────────────────────────────────────────────
        // APPROVED claims are the overtime the business has accepted; submitted
        // ones are reported beside them as still awaiting a decision, never
        // added in. Cost is an estimate at each person's hourly rate of pay —
        // the same hourlyRate() × multiplier payroll uses — on the hours not
        // already taken as time off. A claim settled as time off is hours
        // worked but no cash, so it counts towards hours and not cost.
        [$unpriced] = $this->overtime(
            $companyId, $outlets, $range, $bucket, $zero, $canViewPay, $totals, $byOutlet, $add,
        );

        // ── Labour cost, from payroll (monthly, and only for pay viewers) ─
        $labour = null;
        if ($monthly && $canViewPay) {
            $labour = $this->labour($companyId, $outletIds, $periods, $bucket, $zero, $includeDraftPayroll);

            foreach ($labour['parts']['employer_cost'] as $w => $amount) {
                $totals['labour_cost'][$w] += $amount;
            }
            foreach ($labour['by_outlet'] as $outletId => $series) {
                foreach ($series as $w => $amount) {
                    $add($byOutlet, $outletId, 'labour_cost', $w, $amount);
                }
            }
        }

        $totals = array_map(fn ($series) => array_map(fn ($v) => round($v, 2), $series), $totals);

        $totals['cost_pct']       = array_map(fn ($p, $s) => self::share($p, $s), $totals['purchases'], $totals['sales']);
        $totals['wastage_pct']    = array_map(fn ($p, $s) => self::share($p, $s), $totals['wastage'], $totals['sales']);
        $totals['staff_meal_pct'] = array_map(fn ($p, $s) => self::share($p, $s), $totals['staff_meal'], $totals['sales']);
        $totals['labour_pct']     = array_map(fn ($p, $s) => self::share($p, $s), $totals['labour_cost'], $totals['sales']);

        // ── Department rows, in the company's own department order ────────
        $deptNames = $departments->pluck('name', 'id')->all();
        $deptOrder = array_merge($departments->pluck('id')->all(), [self::UNASSIGNED]);

        $departmentRows = [];
        foreach ($deptOrder as $key) {
            if (! isset($byDept[$key])) continue;

            $row = $this->row(
                $key === self::UNASSIGNED ? 'Unassigned' : ($deptNames[$key] ?? 'Unknown'),
                $byDept[$key], $zero, $cur, $prev, ['sales', 'purchases', 'wastage'],
            );

            if ($row['active']) {
                $departmentRows[] = $row;
            }
        }

        // ── Outlet rows, by name ──────────────────────────────────────────
        $outletKeys = ['sales', 'purchases', 'wastage', 'staff_meal', 'transfers_out', 'transfers_in', 'ot_hours'];
        if ($canViewPay) {
            $outletKeys[] = 'ot_cost';
        }
        if ($labour !== null) {
            $outletKeys[] = 'labour_cost';
        }

        $outletNames = Outlet::withoutGlobalScopes()->whereIn('id', array_keys($byOutlet))->pluck('name', 'id')->all();

        $outletRows = [];
        foreach ($byOutlet as $id => $metrics) {
            $row = $this->row($outletNames[$id] ?? 'Unknown outlet', $metrics, $zero, $cur, $prev, $outletKeys);
            if ($row['active']) {
                $outletRows[] = $row;
            }
        }
        usort($outletRows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        // $upIsGood null: a move that is neither good nor bad news (transfers).
        $kpi = fn (string $key, string $label, array $series, ?bool $upIsGood, string $format = 'money') => [
            'key'        => $key,
            'label'      => $label,
            'current'    => $series[$cur],
            'previous'   => $series[$prev],
            'change'     => $format === 'pct' ? self::points($series[$cur], $series[$prev]) : self::change((float) $series[$cur], (float) $series[$prev]),
            'up_is_good' => $upIsGood,
            'format'     => $format,
        ];

        $kpis = [
            $kpi('sales', 'Sales', $totals['sales'], true),
            $kpi('purchases', 'Purchases', $totals['purchases'], false),
            $kpi('cost_pct', 'Purchase cost % of sales', $totals['cost_pct'], false, 'pct'),
            $kpi('wastage', 'Wastage', $totals['wastage'], false),
            $kpi('wastage_pct', 'Wastage % of sales', $totals['wastage_pct'], false, 'pct'),
            $kpi('staff_meal', 'Staff meals', $totals['staff_meal'], false),
            $kpi('transfers', 'Stock transfers', $totals['transfers'], null),
            $kpi('ot_hours', 'Overtime hours (approved)', $totals['ot_hours'], false, 'hours'),
        ];
        if ($canViewPay) {
            $kpis[] = $kpi('ot_cost', 'Overtime cost (estimated)', $totals['ot_cost'], false);
        }
        if ($labour !== null) {
            $kpis[] = $kpi('labour_cost', 'Labour cost', $totals['labour_cost'], false);
            $kpis[] = $kpi('labour_pct', 'Labour cost % of sales', $totals['labour_pct'], false, 'pct');
        }

        // The trend shows only the periods asked for; the comparison above
        // was taken from the full set.
        $trim   = fn (array $series) => array_values(array_slice($series, -$count));
        $shown  = $trim($periods);
        $labels = array_column($shown, 'label');
        $colors = [
            'sales'      => PurchaseSupplierBreakdown::SERIES[0],
            'purchases'  => PurchaseSupplierBreakdown::SERIES[2],
            'line'       => PurchaseSupplierBreakdown::SERIES[5],
            'wastage'    => PurchaseSupplierBreakdown::SERIES[3],
            'staff_meal' => PurchaseSupplierBreakdown::SERIES[4],
            'transfers'  => PurchaseSupplierBreakdown::SERIES[1],
            'overtime'   => PurchaseSupplierBreakdown::SERIES[6],
            'labour'     => PurchaseSupplierBreakdown::SERIES[7],
            'previous'   => PurchaseSupplierBreakdown::OTHER_COLOR,
        ];

        $costChart = fn (string $key, string $color, ?array $pct) => [
            'labels' => $labels, 'values' => $trim($totals[$key]), 'pct' => $pct === null ? null : $trim($pct),
            'color' => $color, 'line' => $colors['line'],
            'bar_prefix' => 'RM ', 'bar_suffix' => '', 'line_label' => '% of sales', 'line_suffix' => '%',
        ];

        return [
            'granularity'  => $monthly ? self::MONTH : self::WEEK,
            'count'        => $count,
            'can_view_pay' => $canViewPay,
            'periods'      => $shown,
            'current'      => $periods[$cur],
            'previous'     => $periods[$prev],
            'has_data'     => array_sum(array_map('array_sum', array_intersect_key($totals, array_flip([
                'sales', 'purchases', 'wastage', 'staff_meal', 'transfers', 'ot_hours', 'ot_pending_hours', 'labour_cost',
            ])))) > 0,
            'totals'       => array_map($trim, $totals),
            'kpis'         => $kpis,
            'departments'  => $departmentRows,
            'outlets'      => $outletRows,
            'overtime'     => [
                'pending_hours' => ['current' => $totals['ot_pending_hours'][$cur], 'previous' => $totals['ot_pending_hours'][$prev]],
                'unpriced'      => $unpriced[$cur],
            ],
            'labour'       => $labour === null ? null : $this->labourSummary($labour, $cur, $prev),
            'charts'       => [
                'trend' => [
                    'labels' => $labels, 'starts' => array_column($shown, 'start'),
                    'sales' => $trim($totals['sales']), 'purchases' => $trim($totals['purchases']),
                    'cost_pct' => $trim($totals['cost_pct']),
                    'colors' => $colors,
                ],
                'departments' => [
                    'labels'    => array_column($departmentRows, 'name'),
                    'sales'     => array_map(fn ($r) => $r['sales']['current'], $departmentRows),
                    'purchases' => array_map(fn ($r) => $r['purchases']['current'], $departmentRows),
                    'cost_pct'  => array_map(fn ($r) => $r['cost_pct']['current'], $departmentRows),
                    'colors'    => $colors,
                ],
                'wastage'    => $costChart('wastage', $colors['wastage'], $totals['wastage_pct']),
                'staff_meal' => $costChart('staff_meal', $colors['staff_meal'], $totals['staff_meal_pct']),
                // No share-of-sales line: moving stock is not a cost of sales.
                'transfers'  => $costChart('transfers', $colors['transfers'], null),
                // With pay: cost bars and an hours line. Without: hours alone.
                'overtime'   => $canViewPay
                    ? array_merge(
                        $costChart('ot_cost', $colors['overtime'], null),
                        ['pct' => $trim($totals['ot_hours']), 'line_label' => 'Hours', 'line_suffix' => ' h'],
                    )
                    : array_merge(
                        $costChart('ot_hours', $colors['overtime'], null),
                        ['bar_prefix' => '', 'bar_suffix' => ' h'],
                    ),
                'labour'     => $labour === null ? null : $costChart('labour_cost', $colors['labour'], $totals['labour_pct']),
            ],
        ];
    }

    /**
     * Approved overtime hours and estimated cost into $totals and $byOutlet.
     *
     * @return array{0: array<int, int>}  approved claims per period that could not be costed
     */
    private function overtime(
        int $companyId,
        callable $outlets,
        array $range,
        callable $bucket,
        array $zero,
        bool $canViewPay,
        array &$totals,
        array &$byOutlet,
        callable $add,
    ): array {
        $claims = $outlets(OvertimeClaim::withoutGlobalScopes()->where('company_id', $companyId)->whereNull('deleted_at'))
            ->whereIn('status', ['submitted', 'approved'])
            ->whereBetween('claim_date', $range)
            ->get(['id', 'outlet_id', 'employee_id', 'claim_date', 'total_ot_hours', 'hours_taken_off', 'ot_type', 'status', 'settlement']);

        $settings  = CompensationSetting::forCompany($companyId);
        $employees = $canViewPay
            ? Employee::withoutGlobalScopes()
                ->whereIn('id', $claims->pluck('employee_id')->filter()->unique())
                ->get(['id', 'basic_salary', 'pay_type', 'daily_working_hours'])
                ->keyBy('id')
            : collect();

        $unpriced = array_map('intval', $zero);

        foreach ($claims as $claim) {
            if (($w = $bucket($claim->claim_date)) === null) continue;
            $hours = (float) $claim->total_ot_hours;

            if ($claim->status === 'submitted') {
                $totals['ot_pending_hours'][$w] += $hours;
                continue;
            }

            $totals['ot_hours'][$w] += $hours;
            $add($byOutlet, (int) $claim->outlet_id, 'ot_hours', $w, $hours);

            if (! $canViewPay || $claim->settlement === OvertimeClaim::SETTLE_TIME_OFF) continue;

            $employee = $employees[$claim->employee_id] ?? null;
            $rate = $employee ? $settings->hourlyRate(
                $employee->basic_salary !== null ? (float) $employee->basic_salary : null,
                $employee->pay_type,
                $employee->daily_working_hours !== null ? (float) $employee->daily_working_hours : null,
            ) : null;

            if ($rate === null) {
                $unpriced[$w]++;
                continue;
            }

            $cost = max(0.0, $hours - (float) $claim->hours_taken_off) * $rate * $settings->multiplierFor((string) $claim->ot_type);
            $totals['ot_cost'][$w] += $cost;
            $add($byOutlet, (int) $claim->outlet_id, 'ot_cost', $w, $cost);
        }

        return [$unpriced];
    }

    /**
     * Labour cost per month from payroll run lines.
     *
     * ONE LINE PER PERSON PER MONTH. A company can hold a company-wide run and
     * an outlet run for the same month, and a regenerated draft beside the
     * approved one; adding every line would pay the same person twice. The
     * line kept is from the most settled run — paid, then approved, then draft
     * — and the latest generated among equals.
     *
     * Lines carry no outlet id (they snapshot the outlet's NAME), so an outlet
     * run's lines belong to its outlet, and a company-wide run's lines to each
     * employee's current outlet.
     */
    private function labour(int $companyId, array $outletIds, array $periods, callable $bucket, array $zero, bool $includeDrafts): array
    {
        $monthRange = [$periods[0]['start'], $periods[count($periods) - 1]['end']];
        $statuses   = $includeDrafts
            ? [PayrollRun::DRAFT, PayrollRun::APPROVED, PayrollRun::PAID]
            : [PayrollRun::APPROVED, PayrollRun::PAID];
        $rank = [PayrollRun::PAID => 3, PayrollRun::APPROVED => 2, PayrollRun::DRAFT => 1];

        $runs = PayrollRun::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', $statuses)
            ->whereBetween('period_month', $monthRange)
            ->get(['id', 'outlet_id', 'period_month', 'status', 'generated_at'])
            ->keyBy('id');

        $draftsLeftOut = $includeDrafts ? 0 : PayrollRun::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', PayrollRun::DRAFT)
            ->whereBetween('period_month', $monthRange)
            ->when($outletIds, fn ($q) => $q->where(fn ($w) => $w->whereIn('outlet_id', $outletIds)->orWhereNull('outlet_id')))
            ->count();

        $parts = ['basic', 'allowances', 'ot_amount', 'service_charge', 'gross', 'statutory_employer', 'employer_cost'];

        $lines = $runs->isEmpty() ? collect() : PayrollRunLine::withoutGlobalScopes()
            ->whereIn('payroll_run_id', $runs->keys())
            ->get(array_merge(['id', 'payroll_run_id', 'employee_id', 'employee_name'], $parts));

        $outletOf = Employee::withoutGlobalScopes()
            ->whereIn('id', $lines->pluck('employee_id')->filter()->unique())
            ->pluck('outlet_id', 'id');

        $chosen = [];
        foreach ($lines as $line) {
            $run = $runs[$line->payroll_run_id];
            if (($w = $bucket($run->period_month)) === null) continue;

            $outletId = $run->outlet_id ?? ($line->employee_id ? ($outletOf[$line->employee_id] ?? null) : null);
            if ($outletIds && ! in_array((int) $outletId, $outletIds, true)) continue;

            $who   = $line->employee_id ? 'e' . $line->employee_id : 'n' . mb_strtolower((string) $line->employee_name);
            $key   = $w . '|' . $who;
            $score = [$rank[$run->status] ?? 0, (string) $run->generated_at, (int) $run->id];

            if (isset($chosen[$key]) && $chosen[$key]['score'] >= $score) continue;

            $chosen[$key] = [
                'score' => $score, 'w' => $w, 'outlet' => $outletId ? (int) $outletId : null,
                'line' => $line, 'draft' => $run->status === PayrollRun::DRAFT,
            ];
        }

        $sum       = array_fill_keys($parts, $zero);
        $headcount = array_map('intval', $zero);
        $byOutlet  = [];
        $draftUsed = array_fill(0, count($zero), false);

        foreach ($chosen as $c) {
            foreach ($parts as $p) {
                $sum[$p][$c['w']] += (float) $c['line']->{$p};
            }
            $headcount[$c['w']]++;
            $draftUsed[$c['w']] = $draftUsed[$c['w']] || $c['draft'];

            if ($c['outlet'] !== null) {
                $byOutlet[$c['outlet']] ??= $zero;
                $byOutlet[$c['outlet']][$c['w']] += (float) $c['line']->employer_cost;
            }
        }

        return [
            'parts'           => $sum,
            'headcount'       => $headcount,
            'by_outlet'       => $byOutlet,
            'draft_used'      => $draftUsed,
            'drafts_left_out' => $draftsLeftOut,
            'include_drafts'  => $includeDrafts,
        ];
    }

    /** The labour breakdown for the reviewed month against the one before. */
    private function labourSummary(array $labour, int $cur, int $prev): array
    {
        $labels = [
            'basic'              => 'Basic pay',
            'allowances'         => 'Allowances',
            'ot_amount'          => 'Overtime (paid)',
            'service_charge'     => 'Service charge',
            'gross'              => 'Gross pay',
            'statutory_employer' => 'Employer statutory (EPF, SOCSO, EIS…)',
            'employer_cost'      => 'Labour cost (employer cost)',
        ];

        $rows = [];
        foreach ($labels as $key => $label) {
            $now  = round($labour['parts'][$key][$cur], 2);
            $then = round($labour['parts'][$key][$prev], 2);
            $rows[] = ['key' => $key, 'label' => $label, 'current' => $now, 'previous' => $then, 'change' => self::change($now, $then)];
        }

        return [
            'rows'            => $rows,
            'headcount'       => ['current' => $labour['headcount'][$cur], 'previous' => $labour['headcount'][$prev]],
            'draft_used'      => $labour['draft_used'][$cur] || $labour['draft_used'][$prev],
            'drafts_left_out' => $labour['drafts_left_out'],
            'include_drafts'  => $labour['include_drafts'],
        ];
    }

    /**
     * One department or outlet: each metric this period against last, plus
     * purchase cost %, wastage % and labour % of that row's own sales.
     *
     * @param  array<string, array<int, float>>  $metrics
     * @param  array<int, string>  $keys
     */
    private function row(string $name, array $metrics, array $zero, int $cur, int $prev, array $keys): array
    {
        $row    = ['name' => $name, 'active' => false];
        $series = ['sales' => $metrics['sales'] ?? $zero];

        foreach ($keys as $key) {
            $series[$key] = $metrics[$key] ?? $zero;
            $row[$key] = [
                'current'  => round($series[$key][$cur], 2),
                'previous' => round($series[$key][$prev], 2),
                'change'   => self::change($series[$key][$cur], $series[$key][$prev]),
            ];

            if (abs($series[$key][$cur]) > 0.005 || abs($series[$key][$prev]) > 0.005) {
                $row['active'] = true;
            }
        }

        foreach (['cost_pct' => 'purchases', 'wastage_pct' => 'wastage', 'labour_pct' => 'labour_cost'] as $pctKey => $of) {
            if (! in_array($of, $keys, true)) continue;

            $now  = self::share($series[$of][$cur], $series['sales'][$cur]);
            $then = self::share($series[$of][$prev], $series['sales'][$prev]);
            $row[$pctKey] = ['current' => $now, 'previous' => $then, 'change' => self::points($now, $then)];
        }

        return $row;
    }

    /** Percentage change, or null when there is nothing to compare against. */
    private static function change(float $current, float $previous): ?float
    {
        return abs($previous) > 0.005 ? round(($current - $previous) / abs($previous) * 100, 1) : null;
    }

    /** $part as a percentage of $whole, or null with no sales to divide by. */
    private static function share(float $part, float $whole): ?float
    {
        return $whole > 0.005 ? round($part / $whole * 100, 1) : null;
    }

    /** The move between two percentages, in percentage points. */
    private static function points(?float $current, ?float $previous): ?float
    {
        return $current !== null && $previous !== null ? round($current - $previous, 1) : null;
    }
}
