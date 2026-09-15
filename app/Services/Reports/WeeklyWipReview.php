<?php

namespace App\Services\Reports;

use App\Models\Department;
use App\Models\Outlet;
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
 * The figures behind the weekly WIP meeting: one reviewed week against the
 * week before, and a trend of the weeks leading up to it — sales against
 * purchases (overall, by department and by outlet), wastage, staff meals and
 * stock transfers between outlets.
 *
 * WEEKS RUN MONDAY TO SUNDAY, labelled by ISO week number.
 *
 * DEPARTMENTS AND SALES. A sale has no department of its own; its lines carry
 * a sales category, and a department points at the sales category it sells
 * through (Department::sales_category_id). Revenue follows that link. Revenue
 * whose category no department claims — or a sale keyed as a header total
 * with no lines — is reported as "Unassigned" rather than dropped, so the
 * department rows always add up to the sales total.
 *
 * Grouped by the raw date column and bucketed into weeks in PHP: YEARWEEK()
 * and DATE_FORMAT() are MySQL-only and would leave this untestable on SQLite.
 */
class WeeklyWipReview
{
    public const WEEK_OPTIONS = [4, 8, 12];

    private const UNASSIGNED = 'none';

    /**
     * @param  array<int, int>  $outletIds  empty = every outlet the company has
     */
    public function build(int $companyId, array $outletIds, Carbon $week, int $weeks = 8): array
    {
        $weeks   = in_array($weeks, self::WEEK_OPTIONS, true) ? $weeks : 8;
        $current = $week->copy()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $first   = $current->copy()->subWeeks($weeks - 1);
        $range   = [$first->toDateString(), $current->copy()->addDays(6)->toDateString() . ' 23:59:59'];

        $weekList = [];
        for ($i = 0; $i < $weeks; $i++) {
            $start = $first->copy()->addWeeks($i);
            $end   = $start->copy()->addDays(6);

            $weekList[] = [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
                'label' => 'W' . $start->isoWeek() . ' · ' . $start->format('j M'),
                'range' => $start->format('j M') . ' – ' . $end->format('j M Y'),
            ];
        }

        $index = array_flip(array_column($weekList, 'start'));
        $cur   = $weeks - 1;
        $prev  = $weeks - 2;
        $zero  = array_fill(0, $weeks, 0.0);

        $bucket = function ($date) use ($index): ?int {
            $key = Carbon::parse($date)->startOfWeek(CarbonInterface::MONDAY)->toDateString();

            return $index[$key] ?? null;
        };

        $outlets = fn ($query, string $column = 'outlet_id') => $outletIds ? $query->whereIn($column, $outletIds) : $query;

        $totals    = ['sales' => $zero, 'purchases' => $zero, 'wastage' => $zero, 'staff_meal' => $zero, 'transfers' => $zero];
        $byDept    = [];
        $byOutlet  = [];
        $lineSales = $zero;

        $add = function (array &$target, $key, string $metric, int $w, float $amount) use ($zero): void {
            $target[$key][$metric] ??= $zero;
            $target[$key][$metric][$w] += $amount;
        };

        // ── Sales: header totals, for the week and the outlet ─────────────
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

        $totals = array_map(fn ($series) => array_map(fn ($v) => round($v, 2), $series), $totals);

        $costPct    = array_map(fn ($p, $s) => self::share($p, $s), $totals['purchases'], $totals['sales']);
        $wastagePct = array_map(fn ($p, $s) => self::share($p, $s), $totals['wastage'], $totals['sales']);
        $mealPct    = array_map(fn ($p, $s) => self::share($p, $s), $totals['staff_meal'], $totals['sales']);

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
        $outletNames = Outlet::withoutGlobalScopes()->whereIn('id', array_keys($byOutlet))->pluck('name', 'id')->all();

        $outletRows = [];
        foreach ($byOutlet as $id => $metrics) {
            $row = $this->row($outletNames[$id] ?? 'Unknown outlet', $metrics, $zero, $cur, $prev, ['sales', 'purchases', 'wastage', 'staff_meal', 'transfers_out', 'transfers_in']);
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

        $labels = array_column($weekList, 'label');
        $colors = [
            'sales'      => PurchaseSupplierBreakdown::SERIES[0],
            'purchases'  => PurchaseSupplierBreakdown::SERIES[2],
            'line'       => PurchaseSupplierBreakdown::SERIES[5],
            'wastage'    => PurchaseSupplierBreakdown::SERIES[3],
            'staff_meal' => PurchaseSupplierBreakdown::SERIES[4],
            'transfers'  => PurchaseSupplierBreakdown::SERIES[1],
            'previous'   => PurchaseSupplierBreakdown::OTHER_COLOR,
        ];

        return [
            'weeks'       => $weekList,
            'current'     => $weekList[$cur],
            'previous'    => $weekList[$prev],
            'has_data'    => array_sum($totals['sales']) + array_sum($totals['purchases'])
                           + array_sum($totals['wastage']) + array_sum($totals['staff_meal'])
                           + array_sum($totals['transfers']) > 0,
            'totals'      => $totals + ['cost_pct' => $costPct, 'wastage_pct' => $wastagePct, 'staff_meal_pct' => $mealPct],
            'kpis'        => [
                $kpi('sales', 'Sales', $totals['sales'], true),
                $kpi('purchases', 'Purchases', $totals['purchases'], false),
                $kpi('cost_pct', 'Purchase cost % of sales', $costPct, false, 'pct'),
                $kpi('wastage', 'Wastage', $totals['wastage'], false),
                $kpi('wastage_pct', 'Wastage % of sales', $wastagePct, false, 'pct'),
                $kpi('staff_meal', 'Staff meals', $totals['staff_meal'], false),
                $kpi('transfers', 'Stock transfers', $totals['transfers'], null),
            ],
            'departments' => $departmentRows,
            'outlets'     => $outletRows,
            'charts'      => [
                'trend' => [
                    'labels' => $labels, 'starts' => array_column($weekList, 'start'),
                    'sales' => $totals['sales'], 'purchases' => $totals['purchases'], 'cost_pct' => $costPct,
                    'colors' => $colors,
                ],
                'departments' => [
                    'labels'    => array_column($departmentRows, 'name'),
                    'sales'     => array_map(fn ($r) => $r['sales']['current'], $departmentRows),
                    'purchases' => array_map(fn ($r) => $r['purchases']['current'], $departmentRows),
                    'cost_pct'  => array_map(fn ($r) => $r['cost_pct']['current'], $departmentRows),
                    'colors'    => $colors,
                ],
                'wastage' => [
                    'labels' => $labels, 'values' => $totals['wastage'], 'pct' => $wastagePct,
                    'color' => $colors['wastage'], 'line' => $colors['line'],
                ],
                'staff_meal' => [
                    'labels' => $labels, 'values' => $totals['staff_meal'], 'pct' => $mealPct,
                    'color' => $colors['staff_meal'], 'line' => $colors['line'],
                ],
                // No share-of-sales line: moving stock is not a cost of sales.
                'transfers' => [
                    'labels' => $labels, 'values' => $totals['transfers'], 'pct' => null,
                    'color' => $colors['transfers'], 'line' => $colors['line'],
                ],
            ],
        ];
    }

    /**
     * One department or outlet: each metric this week against last, plus
     * purchase cost % and wastage % of that row's own sales.
     *
     * @param  array<string, array<int, float>>  $metrics
     * @param  array<int, string>  $keys
     */
    private function row(string $name, array $metrics, array $zero, int $cur, int $prev, array $keys): array
    {
        $row    = ['name' => $name, 'active' => false];
        $series = [];

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

        foreach (['cost_pct' => 'purchases', 'wastage_pct' => 'wastage'] as $pctKey => $of) {
            if (! isset($series[$of])) continue;

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
