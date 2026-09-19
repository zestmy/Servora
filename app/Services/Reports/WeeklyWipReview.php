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
use App\Models\SalesCategory;
use App\Models\SalesRecord;
use App\Models\SalesTarget;
use App\Models\Section;
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
        $byDept     = [];
        $byOutlet   = [];
        $byCategory = [];
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
            ->get(['id', 'name', 'sales_category_id', 'costs_against_total_sales']);

        // EVERY department on a sales category is measured against that
        // category's sales. Several departments often share one — a kitchen
        // and a pastry section both selling "Food" — and giving the sales to
        // only the first left the rest with RM0 sales, and so no purchase
        // cost % or wastage % at all. A shared category's sales therefore sit
        // on each of its departments, the row names who it shares them with,
        // and department sales can add up to more than total sales — which is
        // why no total is drawn under them. Total sales still count each sale
        // once (it is read off the headers, not these rows).
        $deptsForCategory = [];
        foreach ($departments as $d) {
            if ($d->sales_category_id) {
                $deptsForCategory[$d->sales_category_id][] = $d->id;
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
            foreach ($deptsForCategory[$row->category_id] ?? [self::UNASSIGNED] as $key) {
                $add($byDept, $key, 'sales', $w, (float) $row->amount);
            }
            $add($byCategory, $row->category_id ?: self::UNASSIGNED, 'sales', $w, (float) $row->amount);
            $lineSales[$w] += (float) $row->amount;
        }

        // Header revenue with no lines behind it (a Z-report total, say).
        foreach ($totals['sales'] as $w => $amount) {
            $gap = $amount - $lineSales[$w];
            if ($gap > 0.005) {
                $add($byDept, self::UNASSIGNED, 'sales', $w, $gap);
                $add($byCategory, self::UNASSIGNED, 'sales', $w, $gap);
            }
        }

        // A department set to cost against total sales (consumables, say) is
        // measured against every sale — the same total the headline uses.
        foreach ($departments->where('costs_against_total_sales', true) as $d) {
            foreach ($totals['sales'] as $w => $amount) {
                $add($byDept, $d->id, 'sales', $w, $amount);
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
        $bySection  = [];
        [$unpriced] = $this->overtime(
            $companyId, $outlets, $range, $bucket, $zero, $canViewPay, $totals, $byOutlet, $bySection, $add,
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
        $totals['ot_cost_pct']    = array_map(fn ($p, $s) => self::share($p, $s), $totals['ot_cost'], $totals['sales']);

        // ── Department rows, in the company's own department order ────────
        $deptNames = $departments->pluck('name', 'id')->all();
        $deptOrder = array_merge($departments->pluck('id')->all(), [self::UNASSIGNED]);

        // Which other departments each one shares its sales category with.
        $sharedWith = [];
        foreach ($departments as $d) {
            $peers = array_filter($deptsForCategory[$d->sales_category_id] ?? [], fn ($id) => $id !== $d->id);
            $sharedWith[$d->id] = array_values(array_map(fn ($id) => $deptNames[$id], $peers));
        }

        $departmentRows = [];
        foreach ($deptOrder as $key) {
            if (! isset($byDept[$key])) continue;

            $row = $this->row(
                $key === self::UNASSIGNED ? 'Unassigned' : ($deptNames[$key] ?? 'Unknown'),
                $byDept[$key], $zero, $cur, $prev, ['sales', 'purchases', 'wastage'],
            );
            $row['shared_with'] = $key === self::UNASSIGNED ? [] : ($sharedWith[$key] ?? []);
            $row['total_sales'] = $key !== self::UNASSIGNED && (bool) $departments->firstWhere('id', $key)?->costs_against_total_sales;

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
        // $ofSales: the figure as a % of sales, carried ON the tile beside the
        // amount rather than as a tile of its own — "RM 820 wastage" means
        // little until it sits next to "2.1% of sales".
        $kpi = fn (string $key, string $label, array $series, ?bool $upIsGood, string $format = 'money', ?array $ofSales = null) => [
            'key'        => $key,
            'label'      => $label,
            'current'    => $series[$cur],
            'previous'   => $series[$prev],
            'change'     => $format === 'pct' ? self::points($series[$cur], $series[$prev]) : self::change((float) $series[$cur], (float) $series[$prev]),
            'up_is_good' => $upIsGood,
            'format'     => $format,
            'share'      => $ofSales === null ? null : [
                'current'  => $ofSales[$cur],
                'previous' => $ofSales[$prev],
                'change'   => self::points($ofSales[$cur], $ofSales[$prev]),
            ],
        ];

        $kpis = [
            $kpi('sales', 'Sales', $totals['sales'], true),
            $kpi('purchases', 'Purchases', $totals['purchases'], false, 'money', $totals['cost_pct']),
            $kpi('wastage', 'Wastage', $totals['wastage'], false, 'money', $totals['wastage_pct']),
            $kpi('staff_meal', 'Staff meals', $totals['staff_meal'], false, 'money', $totals['staff_meal_pct']),
            $kpi('transfers', 'Stock transfers', $totals['transfers'], null),
            $kpi('ot_hours', 'Overtime hours (approved)', $totals['ot_hours'], false, 'hours'),
        ];
        if ($canViewPay) {
            $kpis[] = $kpi('ot_cost', 'Overtime cost (estimated)', $totals['ot_cost'], false, 'money', $totals['ot_cost_pct']);
        }
        if ($labour !== null) {
            $kpis[] = $kpi('labour_cost', 'Labour cost', $totals['labour_cost'], false, 'money', $totals['labour_pct']);
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
            'categories'   => $this->categoryRows($companyId, $byCategory, $totals['sales'], $cur, $prev),
            'outlets'      => $outletRows,
            'overtime'     => [
                'pending_hours' => ['current' => $totals['ot_pending_hours'][$cur], 'previous' => $totals['ot_pending_hours'][$prev]],
                'unpriced'      => $unpriced[$cur],
                'sections'      => $this->sectionRows($companyId, $bySection, $zero, $cur, $prev, $canViewPay),
            ],
            'labour'       => $labour === null ? null : $this->labourSummary($labour, $cur, $prev),
            'sales_performance' => $monthly ? null : $this->salesPerformance($companyId, $outletIds, $periods[$cur]['start']),
            'charts'       => [
                'trend' => [
                    'labels' => $labels, 'starts' => array_column($shown, 'start'),
                    'sales' => $trim($totals['sales']), 'purchases' => $trim($totals['purchases']),
                    'cost_pct' => $trim($totals['cost_pct']),
                    'colors' => $colors,
                ],
                'departments' => $this->departmentChart($departmentRows, $colors, $monthly ? 'month' : 'week'),
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
     * Approved overtime hours and estimated cost into $totals, $byOutlet and
     * $bySection.
     *
     * By SECTION as well as outlet because an outlet's overtime is usually
     * one team's: "KLCC did 60 hours" becomes "the kitchen did 52 of them".
     * The section is the employee's CURRENT one — claims do not record it —
     * so somebody moved from the floor to the kitchen brings their history.
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
        array &$bySection,
        callable $add,
    ): array {
        $claims = $outlets(OvertimeClaim::withoutGlobalScopes()->where('company_id', $companyId)->whereNull('deleted_at'))
            ->whereIn('status', ['submitted', 'approved'])
            ->whereBetween('claim_date', $range)
            ->get(['id', 'outlet_id', 'employee_id', 'claim_date', 'total_ot_hours', 'hours_taken_off', 'ot_type', 'status', 'settlement']);

        $settings = CompensationSetting::forCompany($companyId);

        // Salary fields are only read for somebody who may see them.
        $employees = Employee::withoutGlobalScopes()
            ->whereIn('id', $claims->pluck('employee_id')->filter()->unique())
            ->get(array_merge(['id', 'section_id'], $canViewPay ? ['basic_salary', 'pay_type', 'daily_working_hours'] : []))
            ->keyBy('id');

        $unpriced = array_map('intval', $zero);

        foreach ($claims as $claim) {
            if (($w = $bucket($claim->claim_date)) === null) continue;
            $hours = (float) $claim->total_ot_hours;

            if ($claim->status === 'submitted') {
                $totals['ot_pending_hours'][$w] += $hours;
                continue;
            }

            $employee   = $employees[$claim->employee_id] ?? null;
            $sectionKey = $employee?->section_id ?: self::UNASSIGNED;

            $totals['ot_hours'][$w] += $hours;
            $add($byOutlet, (int) $claim->outlet_id, 'ot_hours', $w, $hours);
            $add($bySection, $sectionKey, 'ot_hours', $w, $hours);

            if (! $canViewPay || $claim->settlement === OvertimeClaim::SETTLE_TIME_OFF) continue;

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
            $add($bySection, $sectionKey, 'ot_cost', $w, $cost);
        }

        return [$unpriced];
    }

    /**
     * Overtime per section, in the company's own section order, with staff
     * who have no section last.
     *
     * @param  array<int|string, array<string, array<int, float>>>  $bySection
     */
    private function sectionRows(int $companyId, array $bySection, array $zero, int $cur, int $prev, bool $canViewPay): array
    {
        if ($bySection === []) {
            return [];
        }

        $sections = Section::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name']);

        $names = $sections->pluck('name', 'id')->all();
        $order = array_merge($sections->pluck('id')->all(), [self::UNASSIGNED]);
        $keys  = $canViewPay ? ['ot_hours', 'ot_cost'] : ['ot_hours'];

        $rows = [];
        foreach ($order as $key) {
            if (! isset($bySection[$key])) continue;

            $row = $this->row($key === self::UNASSIGNED ? 'No section' : ($names[$key] ?? 'Unknown section'), $bySection[$key], $zero, $cur, $prev, $keys);
            if ($row['active']) {
                $rows[] = $row;
            }
        }

        return $rows;
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

    /**
     * The weekly sales-performance sheet: sales by day of week and meal period
     * for the reviewed week and the one before, with the variance per day, and
     * month-to-date sales, covers and average check against last month and the
     * same month last year.
     *
     * Meal period is the one each sales record is keyed under; a record with
     * none (or one no longer offered) is All Day. Covers are the records' pax.
     *
     * Month to date runs from the 1st to the reviewed week's Sunday, and each
     * comparison stops on the same day of ITS month, clamped to that month's
     * length — 1st–13th September against 1st–13th August, not all of August.
     */
    private function salesPerformance(int $companyId, array $outletIds, string $weekStart): array
    {
        $options  = SalesRecord::mealPeriodOptions();
        $current  = Carbon::parse($weekStart)->startOfDay();
        $previous = $current->copy()->subWeek();
        $weekEnd  = $current->copy()->addDays(6);

        $sales = fn () => SalesRecord::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->when($outletIds, fn ($q) => $q->whereIn('outlet_id', $outletIds));

        $rows = $sales()
            ->whereBetween('sale_date', [$previous->toDateString(), $weekEnd->toDateString() . ' 23:59:59'])
            ->selectRaw('sale_date as d, meal_period, SUM(total_revenue) as amount')
            ->groupBy('sale_date', 'meal_period')
            ->get();

        $grid = ['current' => [], 'previous' => []];
        foreach ($rows as $row) {
            $date   = Carbon::parse($row->d);
            $which  = $date->gte($current) ? 'current' : 'previous';
            $period = $row->meal_period !== null && isset($options[$row->meal_period]) ? $row->meal_period : 'all_day';
            $dow    = $date->isoWeekday();

            $grid[$which][$period][$dow] = ($grid[$which][$period][$dow] ?? 0.0) + (float) $row->amount;
        }

        // Only the meal periods either week actually traded, in the offered order.
        $periods = array_values(array_filter(
            array_keys($options),
            fn ($p) => isset($grid['current'][$p]) || isset($grid['previous'][$p]),
        ));

        $week = function (string $which, Carbon $start) use ($grid, $periods, $options): array {
            $days  = array_fill(1, 7, 0.0);
            $lines = [];

            foreach ($periods as $p) {
                $values = [];
                for ($d = 1; $d <= 7; $d++) {
                    $values[$d] = round($grid[$which][$p][$d] ?? 0.0, 2);
                    $days[$d]  += $values[$d];
                }
                $lines[] = ['key' => $p, 'label' => $options[$p], 'days' => $values, 'total' => round(array_sum($values), 2)];
            }

            $total = round(array_sum($days), 2);
            foreach ($lines as &$line) {
                $line['share'] = self::share($line['total'], $total);
            }
            unset($line);

            return [
                'label' => 'Week ' . $start->isoWeek(),
                'range' => $start->format('j M') . ' – ' . $start->copy()->addDays(6)->format('j M Y'),
                'lines' => $lines,
                'days'  => array_map(fn ($v) => round($v, 2), $days),
                'total' => $total,
            ];
        };

        $thisWeek = $week('current', $current);
        $lastWeek = $week('previous', $previous);

        $variance = fn (float $now, float $then) => ['amount' => round($now - $then, 2), 'change' => self::change($now, $then)];

        // ── Month to date ─────────────────────────────────────────────────
        $mtdStart = $weekEnd->copy()->startOfMonth();
        $day      = $weekEnd->day;
        $sameDays = fn (Carbon $monthStart) => [$monthStart, $monthStart->copy()->day(min($day, $monthStart->daysInMonth))];

        $windows = [
            'this_month' => ['MTD this month', [$mtdStart, $weekEnd->copy()]],
            'last_month' => ['MTD last month', $sameDays($mtdStart->copy()->subMonthNoOverflow())],
            'last_year'  => ['MTD same month last year', $sameDays($mtdStart->copy()->subYearNoOverflow())],
        ];

        $mtd = [];
        foreach ($windows as $key => [$label, [$from, $to]]) {
            $sum = $sales()
                ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString() . ' 23:59:59'])
                ->selectRaw('SUM(total_revenue) as sales, SUM(pax) as covers')
                ->first();

            $amount = round((float) ($sum->sales ?? 0), 2);
            $covers = (int) ($sum->covers ?? 0);

            $mtd[$key] = [
                'key'       => $key,
                'label'     => $label,
                'range'     => $from->format('jS') . ' – ' . $to->format('jS F Y'),
                'sales'     => $amount,
                'covers'    => $covers,
                'avg_check' => $covers > 0 ? round($amount / $covers, 2) : null,
            ];
        }

        // Each comparison row reads as "this month against that one".
        foreach (['last_month', 'last_year'] as $key) {
            $mtd[$key]['sales_change']  = self::change($mtd['this_month']['sales'], $mtd[$key]['sales']);
            $mtd[$key]['covers_change'] = self::change((float) $mtd['this_month']['covers'], (float) $mtd[$key]['covers']);
            $mtd[$key]['variance']      = round($mtd['this_month']['sales'] - $mtd[$key]['sales'], 2);
        }

        // ── Sales forecast for the month the reviewed week ends in ─────────
        // Straight-line: the month-to-date daily average carried over the days
        // left. The target is Settings > Sales Targets for that month — the
        // outlet's own when one outlet is in view, the company-wide one when
        // several are, and the outlets' targets added up when there is no
        // company-wide figure. A single outlet with no target of its own falls
        // back to the company's, and says so.
        $daysInMonth = $weekEnd->daysInMonth;
        $daysLeft    = $daysInMonth - $day;
        $mtdSales    = $mtd['this_month']['sales'];
        $avgDaily    = $day > 0 ? $mtdSales / $day : 0.0;
        $remaining   = $avgDaily * $daysLeft;

        $targets = SalesTarget::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('period', $weekEnd->format('Y-m'))
            ->where('type', 'monthly')
            ->get(['outlet_id', 'target_revenue']);

        $companyTarget = $targets->first(fn ($t) => $t->outlet_id === null);
        $outletTargets = $targets
            ->filter(fn ($t) => $t->outlet_id !== null)
            ->filter(fn ($t) => ! $outletIds || in_array((int) $t->outlet_id, $outletIds, true));

        [$target, $targetSource] = match (true) {
            count($outletIds) === 1 && $outletTargets->isNotEmpty() => [(float) $outletTargets->first()->target_revenue, 'outlet'],
            $companyTarget !== null                                  => [(float) $companyTarget->target_revenue, 'company'],
            $outletTargets->isNotEmpty()                             => [(float) $outletTargets->sum('target_revenue'), 'outlets'],
            default                                                  => [null, null],
        };

        $forecast = [
            'month_label'        => $weekEnd->format('F Y'),
            'days_in_month'      => $daysInMonth,
            'mtd_days'           => $day,
            'days_left'          => $daysLeft,
            'mtd_sales'          => $mtdSales,
            'target'             => $target,
            'target_source'      => $targetSource,
            'target_count'       => $targetSource === 'outlets' ? $outletTargets->count() : null,
            'balance'            => $target === null ? null : round($mtdSales - $target, 2),
            'avg_daily'          => round($avgDaily, 2),
            'remaining'          => round($remaining, 2),
            'forecast'           => round($mtdSales + $remaining, 2),
            'forecast_vs_target' => $target ? round(($mtdSales + $remaining) / $target * 100, 1) : null,
            'needed_daily'       => $target !== null && $daysLeft > 0 ? round(max(0.0, $target - $mtdSales) / $daysLeft, 2) : null,
        ];

        return [
            'day_names' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'current'   => $thisWeek,
            'previous'  => $lastWeek,
            'variance'  => [
                'days'  => array_map(fn ($d) => $variance($thisWeek['days'][$d], $lastWeek['days'][$d]), range(1, 7)),
                'total' => $variance($thisWeek['total'], $lastWeek['total']),
            ],
            'mtd'       => array_values($mtd),
            'forecast'  => $forecast,
        ];
    }

    /**
     * Sales by sales category for the reviewed period against the one before.
     *
     * Categories that sold something in either period, in the company's own
     * order — an empty row is noise on a projected slide. Header revenue with
     * no lines behind it is Uncategorised, so the rows add up to total sales;
     * under RM 1 it is only the rounding between a record's total and its
     * lines, and is left off rather than shown as a row of sen.
     */
    private function categoryRows(int $companyId, array $byCategory, array $sales, int $cur, int $prev): array
    {
        $categories = SalesCategory::withoutGlobalScopes()->withTrashed()
            ->where('company_id', $companyId)
            ->whereIn('id', array_filter(array_keys($byCategory), 'is_int'))
            ->ordered()
            ->get(['id', 'name']);

        $line = function (string $name, float $current, float $previous): array {
            $current  = round($current, 2);
            $previous = round($previous, 2);

            return [
                'name'     => $name,
                'current'  => $current,
                'previous' => $previous,
                'variance' => round($current - $previous, 2),
                'change'   => self::change($current, $previous),
            ];
        };

        $rows = [];
        foreach ($categories as $c) {
            $s = $byCategory[$c->id]['sales'] ?? null;
            if ($s !== null && (abs($s[$cur]) > 0.005 || abs($s[$prev]) > 0.005)) {
                $rows[] = $line($c->name, $s[$cur], $s[$prev]);
            }
        }

        $none = $byCategory[self::UNASSIGNED]['sales'] ?? null;
        if ($none !== null && (abs($none[$cur]) >= 1 || abs($none[$prev]) >= 1)) {
            $rows[] = $line('Uncategorised', $none[$cur], $none[$prev]);
        }

        return ['rows' => $rows, 'total' => $line('Total', $sales[$cur], $sales[$prev])];
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

        foreach ([
            'cost_pct'       => 'purchases',
            'wastage_pct'    => 'wastage',
            'staff_meal_pct' => 'staff_meal',
            'ot_cost_pct'    => 'ot_cost',
            'labour_pct'     => 'labour_cost',
        ] as $pctKey => $of) {
            if (! in_array($of, $keys, true)) continue;

            $now  = self::share($series[$of][$cur], $series['sales'][$cur]);
            $then = self::share($series[$of][$prev], $series['sales'][$prev]);
            $row[$pctKey] = ['current' => $now, 'previous' => $then, 'change' => self::points($now, $then)];
        }

        return $row;
    }

    /**
     * The department chart, in PERCENTAGES of each department's own sales:
     * purchase cost % this period and last, and wastage % this period.
     *
     * RM bars read badly here: departments sharing a sales category each carry
     * that category's full sales, so their sales bars dwarf everything and the
     * purchases beside them are slivers. A percentage is what the meeting
     * compares across departments anyway. The RM amounts ride along for the
     * tooltip. A department with no sales has no percentage to draw, so it is
     * left off the chart and named instead.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function departmentChart(array $rows, array $colors, string $unit): array
    {
        $charted = array_values(array_filter(
            $rows,
            fn ($r) => $r['cost_pct']['current'] !== null || $r['wastage_pct']['current'] !== null,
        ));

        return [
            'unit'          => $unit,
            'labels'        => array_column($charted, 'name'),
            'cost_pct'      => array_map(fn ($r) => $r['cost_pct']['current'], $charted),
            'cost_pct_prev' => array_map(fn ($r) => $r['cost_pct']['previous'], $charted),
            'wastage_pct'   => array_map(fn ($r) => $r['wastage_pct']['current'], $charted),
            'wastage_pct_prev' => array_map(fn ($r) => $r['wastage_pct']['previous'], $charted),
            'sales'         => array_map(fn ($r) => $r['sales']['current'], $charted),
            'purchases'     => array_map(fn ($r) => $r['purchases']['current'], $charted),
            'wastage'       => array_map(fn ($r) => $r['wastage']['current'], $charted),
            'left_out'      => array_values(array_map(
                fn ($r) => $r['name'],
                array_filter($rows, fn ($r) => $r['cost_pct']['current'] === null && $r['wastage_pct']['current'] === null),
            )),
            // Each chart on its own slide draws only the departments with a bar
            // to show: a department that bought (or wasted) nothing in either
            // period is an empty row that pushes the real ones apart.
            'cost'          => $this->departmentSeries($charted, 'cost_pct', 'purchases'),
            'waste'         => $this->departmentSeries($charted, 'wastage_pct', 'wastage'),
            'colors'        => $colors,
        ];
    }

    private function departmentSeries(array $rows, string $pctKey, string $amountKey): array
    {
        $shown = array_values(array_filter(
            $rows,
            fn ($r) => ($r[$pctKey]['current'] ?? 0) > 0 || ($r[$pctKey]['previous'] ?? 0) > 0,
        ));

        return [
            'labels'   => array_column($shown, 'name'),
            'current'  => array_map(fn ($r) => $r[$pctKey]['current'], $shown),
            'previous' => array_map(fn ($r) => $r[$pctKey]['previous'], $shown),
            'amount'   => array_map(fn ($r) => $r[$amountKey]['current'], $shown),
            'sales'    => array_map(fn ($r) => $r['sales']['current'], $shown),
        ];
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
