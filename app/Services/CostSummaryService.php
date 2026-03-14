<?php

namespace App\Services;

use App\Models\Department;
use App\Models\OutletTransferLine;
use App\Models\PurchaseRecord;
use App\Models\SalesCategory;
use App\Models\SalesRecordLine;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Models\StaffMealRecord;
use App\Models\StaffMealRecordLine;
use App\Models\WastageRecord;
use App\Models\WastageRecordLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CostSummaryService
{
    /**
     * Generate a cost summary for the given period.
     *
     * Groups purchases, stock, wastage and staff meals by DEPARTMENT → SALES CATEGORY.
     * Revenue comes from SalesRecordLines grouped by sales_category_id.
     * Departments without a sales_category_id roll into "Non-Revenue" totals.
     *
     * For monthly mode: pass $period as 'Y-m', leave $startDate/$endDate null.
     * For weekly/custom mode: pass $startDate and $endDate (stock takes will be skipped).
     */
    public function generate(string $period, ?int $outletId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $isCustomRange = $startDate && $endDate;

        if ($isCustomRange) {
            $from = Carbon::parse($startDate)->startOfDay();
            $to = Carbon::parse($endDate)->endOfDay();
        } else {
            $date = Carbon::createFromFormat('Y-m', $period);
            $from = $date->copy()->startOfMonth();
            $to = $date->copy()->endOfMonth();
        }

        $companyId = auth()->user()->company_id;

        // ── Build sales category groups for display rows ──
        $salesCategories = SalesCategory::withTrashed()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Map department_id → sales_category_id for cost allocation
        $deptToSalesCat = Department::where('company_id', $companyId)
            ->whereNotNull('sales_category_id')
            ->pluck('sales_category_id', 'id');

        // ── Revenue (from SalesRecordLines grouped by sales_category_id) ──
        $revenueBySalesCat = $this->getRevenueBySalesCategory($from, $to, $outletId);

        // ── Purchases (from PurchaseRecords grouped by department → sales category) ──
        $purchasesByDept = $this->getPurchasesByDepartment($from, $to, $outletId);

        // ── Stock Takes (grouped by department → sales category) ──
        if ($isCustomRange) {
            $openingByDept = collect();
            $closingByDept = collect();
        } else {
            $openingByDept = $this->getStockValuesByDepartment($period, $outletId, 'opening');
            $closingByDept = $this->getStockValuesByDepartment($period, $outletId, 'closing');
        }

        // ── Transfers (by department) ──
        $transferInByDept = $this->getTransfersByDepartment($from, $to, $outletId, 'in');
        $transferOutByDept = $this->getTransfersByDepartment($from, $to, $outletId, 'out');

        // ── Build per-sales-category summary rows ──
        $categories = [];
        $totals = [
            'revenue'       => 0,
            'purchases'     => 0,
            'transfer_in'   => 0,
            'transfer_out'  => 0,
            'opening_stock' => 0,
            'closing_stock' => 0,
            'cogs'          => 0,
            'cost_pct'      => 0,
            'wastage'       => 0,
            'staff_meals'   => 0,
        ];

        // Track which sales categories have departments mapped
        $salesCatDeptIds = [];
        foreach ($deptToSalesCat as $deptId => $scId) {
            $salesCatDeptIds[$scId][] = $deptId;
        }

        // Active sales categories as report rows
        $activeSalesCats = $salesCategories->where('is_active', true)->whereNull('deleted_at');

        foreach ($activeSalesCats as $sc) {
            $revenue = (float) ($revenueBySalesCat[$sc->id] ?? 0);

            // Sum purchases from departments mapped to this sales category
            $purchases = 0;
            $opening = 0;
            $closing = 0;
            $deptIds = $salesCatDeptIds[$sc->id] ?? [];

            foreach ($deptIds as $deptId) {
                $purchases += (float) ($purchasesByDept[$deptId] ?? 0);
                $opening += (float) ($openingByDept[$deptId] ?? 0);
                $closing += (float) ($closingByDept[$deptId] ?? 0);
            }

            // Transfers (by department → sales category)
            $transferIn = 0;
            $transferOut = 0;
            foreach ($deptIds as $deptId) {
                $transferIn += (float) ($transferInByDept[$deptId] ?? 0);
                $transferOut += (float) ($transferOutByDept[$deptId] ?? 0);
            }

            $cogs = $opening + $purchases + $transferIn - $transferOut - $closing;
            $costPct = $revenue > 0 ? round(($cogs / $revenue) * 100, 1) : 0;

            $row = [
                'id'            => $sc->id,
                'name'          => $sc->name,
                'color'         => $sc->color,
                'type'          => $sc->type,
                'revenue'       => round($revenue, 2),
                'purchases'     => round($purchases, 2),
                'transfer_in'   => round($transferIn, 2),
                'transfer_out'  => round($transferOut, 2),
                'opening_stock' => round($opening, 2),
                'closing_stock' => round($closing, 2),
                'cogs'          => round($cogs, 2),
                'cost_pct'      => $costPct,
            ];

            $categories[] = $row;

            foreach (['revenue', 'purchases', 'transfer_in', 'transfer_out', 'opening_stock', 'closing_stock', 'cogs'] as $field) {
                $totals[$field] += $row[$field];
            }
        }

        // ── Roll in non-revenue costs (departments without sales category) ──
        $mappedDeptIds = $deptToSalesCat->keys()->toArray();
        $nonRevenuePurchases = 0;
        $nonRevenueOpening = 0;
        $nonRevenueClosing = 0;

        foreach ($purchasesByDept as $deptId => $amount) {
            if (! in_array($deptId, $mappedDeptIds)) {
                $nonRevenuePurchases += (float) $amount;
            }
        }
        // Also include purchases with no department (null)
        $nonRevenuePurchases += (float) ($purchasesByDept[0] ?? 0);

        foreach ($openingByDept as $deptId => $amount) {
            if (! in_array($deptId, $mappedDeptIds)) {
                $nonRevenueOpening += (float) $amount;
            }
        }
        foreach ($closingByDept as $deptId => $amount) {
            if (! in_array($deptId, $mappedDeptIds)) {
                $nonRevenueClosing += (float) $amount;
            }
        }

        // Roll in unassigned transfers (dept 0)
        $nonRevenueTransferIn = (float) ($transferInByDept[0] ?? 0);
        $nonRevenueTransferOut = (float) ($transferOutByDept[0] ?? 0);

        $nonRevenueCogs = $nonRevenueOpening + $nonRevenuePurchases + $nonRevenueTransferIn - $nonRevenueTransferOut - $nonRevenueClosing;
        $totals['purchases']     += round($nonRevenuePurchases, 2);
        $totals['transfer_in']   += round($nonRevenueTransferIn, 2);
        $totals['transfer_out']  += round($nonRevenueTransferOut, 2);
        $totals['opening_stock'] += round($nonRevenueOpening, 2);
        $totals['closing_stock'] += round($nonRevenueClosing, 2);
        $totals['cogs']          += round($nonRevenueCogs, 2);

        // Overall cost % = total COGS / total revenue
        $totals['cost_pct'] = $totals['revenue'] > 0
            ? round(($totals['cogs'] / $totals['revenue']) * 100, 1)
            : 0;

        // ── Wastage & Staff Meal totals (reference only) ──
        $totals['wastage']     = $this->getWastageTotalCost($from, $to, $outletId);
        $totals['staff_meals'] = $this->getStaffMealTotalCost($from, $to, $outletId);

        // ── Detailed breakdowns by item ──
        $wastageDetail    = $this->getItemDetail('wastage', $from, $to, $outletId);
        $staffMealsDetail = $this->getItemDetail('staff_meal', $from, $to, $outletId);

        return [
            'categories'         => $categories,
            'totals'             => $totals,
            'wastage_detail'     => $wastageDetail,
            'staff_meals_detail' => $staffMealsDetail,
            'period'             => $period,
            'outlet_id'          => $outletId,
            'is_custom_range'    => $isCustomRange,
        ];
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function getRevenueBySalesCategory(Carbon $from, Carbon $to, ?int $outletId): Collection
    {
        $query = SalesRecordLine::query()
            ->join('sales_records', 'sales_records.id', '=', 'sales_record_lines.sales_record_id')
            ->whereBetween('sales_records.sale_date', [$from, $to])
            ->whereNull('sales_records.deleted_at');

        if ($outletId) {
            $query->where('sales_records.outlet_id', $outletId);
        }

        return $query
            ->selectRaw('sales_record_lines.sales_category_id as cat_id, SUM(sales_record_lines.total_revenue) as total')
            ->whereNotNull('sales_record_lines.sales_category_id')
            ->groupBy('cat_id')
            ->pluck('total', 'cat_id');
    }

    private function getPurchasesByDepartment(Carbon $from, Carbon $to, ?int $outletId): Collection
    {
        $query = PurchaseRecord::query()
            ->whereBetween('purchase_date', [$from, $to]);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        return $query
            ->selectRaw('COALESCE(department_id, 0) as dept_id, SUM(total_amount) as total')
            ->groupBy('dept_id')
            ->pluck('total', 'dept_id');
    }

    private function getWastageTotalCost(Carbon $from, Carbon $to, ?int $outletId): float
    {
        $query = WastageRecord::whereBetween('wastage_date', [$from, $to]);
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }
        return round((float) $query->sum('total_cost'), 2);
    }

    private function getStaffMealTotalCost(Carbon $from, Carbon $to, ?int $outletId): float
    {
        $query = StaffMealRecord::whereBetween('meal_date', [$from, $to]);
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }
        return round((float) $query->sum('total_cost'), 2);
    }

    /**
     * Get item-level detail for wastage or staff meals, grouped by category.
     */
    private function getItemDetail(string $type, Carbon $from, Carbon $to, ?int $outletId): array
    {
        if ($type === 'wastage') {
            $lineModel    = WastageRecordLine::class;
            $masterTable  = 'wastage_records';
            $lineTable    = 'wastage_record_lines';
            $fkField      = 'wastage_record_id';
            $dateField    = 'wastage_date';
        } else {
            $lineModel    = StaffMealRecordLine::class;
            $masterTable  = 'staff_meal_records';
            $lineTable    = 'staff_meal_record_lines';
            $fkField      = 'staff_meal_record_id';
            $dateField    = 'meal_date';
        }

        // Ingredient-based lines
        $ingredientLines = $lineModel::query()
            ->join($masterTable, "{$masterTable}.id", '=', "{$lineTable}.{$fkField}")
            ->join('ingredients', 'ingredients.id', '=', "{$lineTable}.ingredient_id")
            ->join('ingredient_categories', 'ingredient_categories.id', '=', 'ingredients.ingredient_category_id')
            ->leftJoin('ingredient_categories as parent_cat', 'parent_cat.id', '=', 'ingredient_categories.parent_id')
            ->leftJoin('units_of_measure', 'units_of_measure.id', '=', "{$lineTable}.uom_id")
            ->leftJoin('departments', 'departments.id', '=', "{$masterTable}.department_id")
            ->whereBetween("{$masterTable}.{$dateField}", [$from, $to])
            ->whereNull("{$masterTable}.deleted_at")
            ->whereNotNull("{$lineTable}.ingredient_id")
            ->when($outletId, fn ($q) => $q->where("{$masterTable}.outlet_id", $outletId))
            ->selectRaw("
                ingredients.name as item_name,
                'ingredient' as item_type,
                ingredients.is_prep,
                COALESCE(departments.name, COALESCE(parent_cat.name, ingredient_categories.name)) as category_name,
                units_of_measure.abbreviation as uom,
                SUM({$lineTable}.quantity) as total_qty,
                SUM({$lineTable}.total_cost) as total_cost
            ")
            ->groupBy('ingredients.id', 'ingredients.name', 'ingredients.is_prep', 'category_name', 'units_of_measure.abbreviation')
            ->orderBy('category_name')
            ->orderBy('item_name')
            ->get();

        // Recipe-based lines
        $recipeLines = $lineModel::query()
            ->join($masterTable, "{$masterTable}.id", '=', "{$lineTable}.{$fkField}")
            ->join('recipes', 'recipes.id', '=', "{$lineTable}.recipe_id")
            ->leftJoin('units_of_measure', 'units_of_measure.id', '=', "{$lineTable}.uom_id")
            ->whereBetween("{$masterTable}.{$dateField}", [$from, $to])
            ->whereNull("{$masterTable}.deleted_at")
            ->whereNotNull("{$lineTable}.recipe_id")
            ->when($outletId, fn ($q) => $q->where("{$masterTable}.outlet_id", $outletId))
            ->selectRaw("
                recipes.name as item_name,
                'recipe' as item_type,
                0 as is_prep,
                'Recipes' as category_name,
                units_of_measure.abbreviation as uom,
                SUM({$lineTable}.quantity) as total_qty,
                SUM({$lineTable}.total_cost) as total_cost
            ")
            ->groupBy('recipes.id', 'recipes.name', 'units_of_measure.abbreviation')
            ->orderBy('item_name')
            ->get();

        // Merge and group by category
        $allLines = $ingredientLines->concat($recipeLines);
        $groups = [];
        $grandTotal = 0;

        foreach ($allLines as $line) {
            $catName = $line->category_name ?: 'Uncategorized';
            if (!isset($groups[$catName])) {
                $groups[$catName] = ['items' => [], 'total' => 0];
            }

            $cost = round((float) $line->total_cost, 2);
            $groups[$catName]['items'][] = [
                'name'      => $line->item_name,
                'type'      => $line->item_type,
                'is_prep'   => (bool) $line->is_prep,
                'uom'       => $line->uom ?? '',
                'quantity'  => round((float) $line->total_qty, 4),
                'total_cost' => $cost,
            ];
            $groups[$catName]['total'] += $cost;
            $grandTotal += $cost;
        }

        // Round group totals
        foreach ($groups as &$g) {
            $g['total'] = round($g['total'], 2);
        }

        return [
            'groups' => $groups,
            'total'  => round($grandTotal, 2),
        ];
    }

    /**
     * Transfers grouped by department.
     * Outlet transfers don't carry department_id, so the total is keyed under 0 (unassigned).
     * It will roll into non-revenue totals unless specifically distributed later.
     */
    private function getTransfersByDepartment(Carbon $from, Carbon $to, ?int $outletId, string $direction): Collection
    {
        if (! $outletId) {
            return collect();
        }

        $query = OutletTransferLine::query()
            ->join('outlet_transfers', 'outlet_transfers.id', '=', 'outlet_transfer_lines.outlet_transfer_id')
            ->whereBetween('outlet_transfers.transfer_date', [$from, $to])
            ->whereNull('outlet_transfers.deleted_at')
            ->where('outlet_transfers.status', 'received');

        if ($direction === 'in') {
            $query->where('outlet_transfers.to_outlet_id', $outletId);
        } else {
            $query->where('outlet_transfers.from_outlet_id', $outletId);
        }

        $total = (float) $query->selectRaw('SUM(outlet_transfer_lines.quantity * outlet_transfer_lines.unit_cost) as total')
            ->value('total');

        return $total > 0 ? collect([0 => $total]) : collect();
    }

    private function getStockValuesByDepartment(string $period, ?int $outletId, string $type): Collection
    {
        if ($type === 'opening') {
            $targetPeriod = Carbon::createFromFormat('Y-m', $period)->subMonth()->format('Y-m');
        } else {
            $targetPeriod = $period;
        }

        $targetDate = Carbon::createFromFormat('Y-m', $targetPeriod);

        $stQuery = StockTake::query()
            ->where('status', 'completed')
            ->whereYear('stock_take_date', $targetDate->year)
            ->whereMonth('stock_take_date', $targetDate->month);

        if ($outletId) {
            $stQuery->where('outlet_id', $outletId);
        }

        // Group stock takes by department
        $stockTakes = $stQuery->get();

        $result = collect();
        foreach ($stockTakes as $st) {
            $deptId = $st->department_id ?? 0;
            $stValue = (float) StockTakeLine::where('stock_take_id', $st->id)
                ->selectRaw('SUM(actual_quantity * unit_cost) as total')
                ->value('total');
            $result[$deptId] = ($result[$deptId] ?? 0) + $stValue;
        }

        return $result;
    }

}
