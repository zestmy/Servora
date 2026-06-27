<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Lightweight current-stock and usage figures for the Par Levels screen.
 *
 * Mirrors the Stock Balance (Product) report's movement model so the numbers
 * match what users already see there: a completed stock take is the opening
 * balance, and purchases / transfers / wastage recorded AFTER that count are
 * applied on top. Quantities are taken as-is (base UOM), exactly as the report
 * treats them.
 */
class StockOnHandService
{
    /**
     * Current on-hand quantity per ingredient for one outlet.
     *
     * @param  int[]  $ingredientIds
     * @return array<int,float>  [ingredient_id => qty]
     */
    public static function currentForOutlet(array $ingredientIds, int $outletId): array
    {
        $ingredientIds = array_values(array_unique(array_map('intval', $ingredientIds)));
        if (empty($ingredientIds) || $outletId <= 0) {
            return [];
        }

        $o = (int) $outletId;

        // Date of the latest completed stock take for this ingredient + outlet.
        $lastDate = "COALESCE((
            SELECT MAX(st.stock_take_date)
            FROM stock_takes st
            JOIN stock_take_lines stl2 ON stl2.stock_take_id = st.id
            WHERE stl2.ingredient_id = ingredients.id
              AND st.outlet_id = {$o} AND st.status = 'completed'
        ), '1900-01-01')";

        $opening = "COALESCE((
            SELECT stl.actual_quantity
            FROM stock_take_lines stl
            JOIN stock_takes st ON st.id = stl.stock_take_id
            WHERE stl.ingredient_id = ingredients.id
              AND st.outlet_id = {$o} AND st.status = 'completed'
            ORDER BY st.stock_take_date DESC, stl.id DESC
            LIMIT 1
        ), 0)";

        $purchases = "COALESCE((
            SELECT SUM(prl.quantity)
            FROM purchase_record_lines prl
            JOIN purchase_records pr ON pr.id = prl.purchase_record_id
            WHERE prl.ingredient_id = ingredients.id
              AND pr.outlet_id = {$o} AND pr.deleted_at IS NULL
              AND pr.purchase_date > {$lastDate}
        ), 0)";

        $transfersIn = "COALESCE((
            SELECT SUM(otl.quantity)
            FROM outlet_transfer_lines otl
            JOIN outlet_transfers ot ON ot.id = otl.outlet_transfer_id
            WHERE otl.ingredient_id = ingredients.id
              AND ot.to_outlet_id = {$o} AND ot.deleted_at IS NULL
              AND ot.transfer_date > {$lastDate}
        ), 0)";

        $transfersOut = "COALESCE((
            SELECT SUM(otl.quantity)
            FROM outlet_transfer_lines otl
            JOIN outlet_transfers ot ON ot.id = otl.outlet_transfer_id
            WHERE otl.ingredient_id = ingredients.id
              AND ot.from_outlet_id = {$o} AND ot.deleted_at IS NULL
              AND ot.transfer_date > {$lastDate}
        ), 0)";

        $wastage = "COALESCE((
            SELECT SUM(wrl.quantity)
            FROM wastage_record_lines wrl
            JOIN wastage_records wr ON wr.id = wrl.wastage_record_id
            WHERE wrl.ingredient_id = ingredients.id
              AND wr.outlet_id = {$o} AND wr.deleted_at IS NULL
              AND wr.wastage_date > {$lastDate}
        ), 0)";

        $rows = DB::table('ingredients')
            ->whereIn('ingredients.id', $ingredientIds)
            ->selectRaw("ingredients.id as id, ({$opening} + {$purchases} + {$transfersIn} - {$transfersOut} - {$wastage}) as on_hand")
            ->pluck('on_hand', 'id')
            ->toArray();

        return array_map('floatval', $rows);
    }

    /**
     * Average purchased quantity per month per ingredient over the last N months,
     * for one outlet. Used to suggest a sensible par level.
     *
     * @param  int[]  $ingredientIds
     * @return array<int,float>  [ingredient_id => avg per month]
     */
    public static function monthlyPurchaseAverage(array $ingredientIds, int $outletId, int $months = 3): array
    {
        $ingredientIds = array_values(array_unique(array_map('intval', $ingredientIds)));
        if (empty($ingredientIds) || $outletId <= 0 || $months <= 0) {
            return [];
        }

        $since = now()->subMonths($months)->toDateString();

        $totals = DB::table('purchase_record_lines as prl')
            ->join('purchase_records as pr', 'pr.id', '=', 'prl.purchase_record_id')
            ->whereIn('prl.ingredient_id', $ingredientIds)
            ->where('pr.outlet_id', $outletId)
            ->whereNull('pr.deleted_at')
            ->where('pr.purchase_date', '>=', $since)
            ->groupBy('prl.ingredient_id')
            ->selectRaw('prl.ingredient_id as id, SUM(prl.quantity) as total')
            ->pluck('total', 'id')
            ->toArray();

        $result = [];
        foreach ($totals as $id => $total) {
            $result[(int) $id] = round(floatval($total) / $months, 2);
        }

        return $result;
    }
}
