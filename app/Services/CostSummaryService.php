<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\OutletTransfer;
use App\Models\OutletTransferLine;
use App\Models\PurchaseRecord;
use App\Models\PurchaseRecordLine;
use App\Models\Recipe;
use App\Models\SalesRecord;
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
     * Generate a monthly cost summary (P&L style) for the given period.
     *
     * Returns an array with keys:
     *  - categories: [{id, name, color, type, revenue, purchases, transfer_in, transfer_out,
     *                  opening_stock, closing_stock, cogs, cost_pct}]
     *  - totals: same structure + wastage, staff_meals (reference totals only)
     *  - wastage_detail: [{name, type, category, quantity, uom, total_cost, reason}]
     *  - staff_meals_detail: [{name, type, category, quantity, uom, total_cost, reason}]
     *  - period: 'YYYY-MM'
     *  - outlet_id: int|null
     */
    public function generate(string $period, ?int $outletId = null): array
    {
        $date = Carbon::createFromFormat('Y-m', $period);
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        $companyId = auth()->user()->company_id;

        // Get ALL active root categories (revenue + non-revenue)
        $allRootCategories = IngredientCategory::roots()
            ->active()
            ->ordered()
            ->with('children')
            ->get();

        // Separate revenue categories (shown as rows) from non-revenue (rolled into totals only)
        $revenueCategoryGroups = [];
        $nonRevenueCategoryGroups = [];

        foreach ($allRootCategories as $root) {
            $ids = collect([$root->id]);
            foreach ($root->children as $child) {
                $ids->push($child->id);
            }
            $group = [
                'name'  => $root->name,
                'color' => $root->color,
                'type'  => $root->type,
                'ids'   => $ids->toArray(),
            ];

            if ($root->is_revenue) {
                $revenueCategoryGroups[$root->id] = $group;
            } else {
                $nonRevenueCategoryGroups[$root->id] = $group;
            }
        }

        // categoryGroups used for displayed rows = revenue only
        $categoryGroups = $revenueCategoryGroups;

        // ── Revenue (from SalesRecordLines grouped by ingredient_category_id) ──
        $revenueByCategory = $this->getRevenue($startOfMonth, $endOfMonth, $outletId);

        // ── Purchases (from PurchaseRecordLines → ingredient → ingredient_category_id) ──
        $purchasesByCategory = $this->getPurchases($startOfMonth, $endOfMonth, $outletId);

        // ── Transfers ──
        $transferInByCategory = $this->getTransfers($startOfMonth, $endOfMonth, $outletId, 'in');
        $transferOutByCategory = $this->getTransfers($startOfMonth, $endOfMonth, $outletId, 'out');

        // ── Stock Takes (opening = previous month's completed, closing = this month's completed) ──
        $openingByCategory = $this->getStockValues($period, $outletId, 'opening');
        $closingByCategory = $this->getStockValues($period, $outletId, 'closing');

        // ── Build per-category summary rows ──
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

        foreach ($categoryGroups as $rootId => $group) {
            $ids = $group['ids'];

            $revenue      = $this->sumForIds($revenueByCategory, $ids);
            $purchases    = $this->sumForIds($purchasesByCategory, $ids);
            $transferIn   = $this->sumForIds($transferInByCategory, $ids);
            $transferOut  = $this->sumForIds($transferOutByCategory, $ids);
            $opening      = $this->sumForIds($openingByCategory, $ids);
            $closing      = $this->sumForIds($closingByCategory, $ids);

            // COGS = Opening + Purchases + Transfer In - Transfer Out - Closing
            $cogs = $opening + $purchases + $transferIn - $transferOut - $closing;
            $costPct = $revenue > 0 ? round(($cogs / $revenue) * 100, 1) : 0;

            $row = [
                'id'            => $rootId,
                'name'          => $group['name'],
                'color'         => $group['color'],
                'type'          => $group['type'],
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

        // Roll non-revenue category costs (consumables, packaging, etc.) into totals
        foreach ($nonRevenueCategoryGroups as $group) {
            $ids = $group['ids'];
            $purchases    = $this->sumForIds($purchasesByCategory, $ids);
            $transferIn   = $this->sumForIds($transferInByCategory, $ids);
            $transferOut  = $this->sumForIds($transferOutByCategory, $ids);
            $opening      = $this->sumForIds($openingByCategory, $ids);
            $closing      = $this->sumForIds($closingByCategory, $ids);
            $cogs         = $opening + $purchases + $transferIn - $transferOut - $closing;

            $totals['purchases']     += round($purchases, 2);
            $totals['transfer_in']   += round($transferIn, 2);
            $totals['transfer_out']  += round($transferOut, 2);
            $totals['opening_stock'] += round($opening, 2);
            $totals['closing_stock'] += round($closing, 2);
            $totals['cogs']          += round($cogs, 2);
        }

        // Overall cost % = total COGS (all categories) / total revenue
        $totals['cost_pct'] = $totals['revenue'] > 0
            ? round(($totals['cogs'] / $totals['revenue']) * 100, 1)
            : 0;

        // ── Wastage & Staff Meal totals (reference only) ──
        $totals['wastage']     = $this->getWastageTotalCost($startOfMonth, $endOfMonth, $outletId);
        $totals['staff_meals'] = $this->getStaffMealTotalCost($startOfMonth, $endOfMonth, $outletId);

        // ── Detailed breakdowns by item ──
        $wastageDetail    = $this->getItemDetail('wastage', $startOfMonth, $endOfMonth, $outletId);
        $staffMealsDetail = $this->getItemDetail('staff_meal', $startOfMonth, $endOfMonth, $outletId);

        return [
            'categories'        => $categories,
            'totals'            => $totals,
            'wastage_detail'    => $wastageDetail,
            'staff_meals_detail' => $staffMealsDetail,
            'period'            => $period,
            'outlet_id'         => $outletId,
        ];
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function getRevenue(Carbon $from, Carbon $to, ?int $outletId): Collection
    {
        $query = SalesRecordLine::query()
            ->join('sales_records', 'sales_records.id', '=', 'sales_record_lines.sales_record_id')
            ->leftJoin('sales_categories', 'sales_categories.id', '=', 'sales_record_lines.sales_category_id')
            ->whereBetween('sales_records.sale_date', [$from, $to])
            ->whereNull('sales_records.deleted_at');

        if ($outletId) {
            $query->where('sales_records.outlet_id', $outletId);
        }

        return $query
            ->selectRaw('COALESCE(sales_record_lines.ingredient_category_id, sales_categories.ingredient_category_id) as cat_id, SUM(sales_record_lines.total_revenue) as total')
            ->groupBy('cat_id')
            ->pluck('total', 'cat_id');
    }

    private function getPurchases(Carbon $from, Carbon $to, ?int $outletId): Collection
    {
        $query = PurchaseRecordLine::query()
            ->join('purchase_records', 'purchase_records.id', '=', 'purchase_record_lines.purchase_record_id')
            ->join('ingredients', 'ingredients.id', '=', 'purchase_record_lines.ingredient_id')
            ->whereBetween('purchase_records.purchase_date', [$from, $to])
            ->whereNull('purchase_records.deleted_at');

        if ($outletId) {
            $query->where('purchase_records.outlet_id', $outletId);
        }

        return $query
            ->selectRaw('ingredients.ingredient_category_id as cat_id, SUM(purchase_record_lines.total_cost) as total')
            ->groupBy('cat_id')
            ->pluck('total', 'cat_id');
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
     * Returns: [ 'groups' => [ category_name => [ 'items' => [...], 'total' => float ] ], 'total' => float ]
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
            ->whereBetween("{$masterTable}.{$dateField}", [$from, $to])
            ->whereNull("{$masterTable}.deleted_at")
            ->whereNotNull("{$lineTable}.ingredient_id")
            ->when($outletId, fn ($q) => $q->where("{$masterTable}.outlet_id", $outletId))
            ->selectRaw("
                ingredients.name as item_name,
                'ingredient' as item_type,
                ingredients.is_prep,
                COALESCE(parent_cat.name, ingredient_categories.name) as category_name,
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

    private function getTransfers(Carbon $from, Carbon $to, ?int $outletId, string $direction): Collection
    {
        if (! $outletId) {
            // Company-wide: transfers between own outlets net to zero, return empty
            return collect();
        }

        $query = OutletTransferLine::query()
            ->join('outlet_transfers', 'outlet_transfers.id', '=', 'outlet_transfer_lines.outlet_transfer_id')
            ->join('ingredients', 'ingredients.id', '=', 'outlet_transfer_lines.ingredient_id')
            ->whereBetween('outlet_transfers.transfer_date', [$from, $to])
            ->whereNull('outlet_transfers.deleted_at')
            ->where('outlet_transfers.status', 'received');

        if ($direction === 'in') {
            $query->where('outlet_transfers.to_outlet_id', $outletId);
        } else {
            $query->where('outlet_transfers.from_outlet_id', $outletId);
        }

        return $query
            ->selectRaw('ingredients.ingredient_category_id as cat_id, SUM(outlet_transfer_lines.quantity * outlet_transfer_lines.unit_cost) as total')
            ->groupBy('cat_id')
            ->pluck('total', 'cat_id');
    }

    private function getStockValues(string $period, ?int $outletId, string $type): Collection
    {
        // opening = previous month's completed stock take
        // closing = current month's completed stock take
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

        $stockTakeIds = $stQuery->pluck('id');

        if ($stockTakeIds->isEmpty()) {
            return collect();
        }

        return StockTakeLine::query()
            ->whereIn('stock_take_id', $stockTakeIds)
            ->join('ingredients', 'ingredients.id', '=', 'stock_take_lines.ingredient_id')
            ->selectRaw('ingredients.ingredient_category_id as cat_id, SUM(stock_take_lines.actual_quantity * stock_take_lines.unit_cost) as total')
            ->groupBy('cat_id')
            ->pluck('total', 'cat_id');
    }

    private function sumForIds(Collection $data, array $ids): float
    {
        $sum = 0;
        foreach ($ids as $id) {
            $sum += (float) ($data[$id] ?? 0);
        }
        return $sum;
    }
}
