<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;

/**
 * Spend per supplier, from any already-scoped PurchaseCapture query.
 *
 * Pulled out of PurchaseSupplierSummaryController so the PDF export and the
 * on-screen chart run the SAME grouping — a capture either points at a
 * Supplier row or carries a typed name, and the same vendor can arrive both
 * ways, so the SQL groups on both columns and this merges them in PHP on the
 * name the reader recognises. Two independent copies of that merge would be
 * two independent places for "Fresh Meats" linked and "Fresh Meats" typed to
 * disagree about whether they are one supplier or two — which is exactly the
 * kind of drift that makes a chart and its export tell different stories
 * about the same range.
 */
class PurchaseSupplierBreakdown
{
    /**
     * Series colours, shared by the screen's Chart.js panel and the PDF's
     * table-drawn bars — walked deliberately away from the semantic hues
     * (green/amber/red) this app uses for "healthy/watch/over budget", so a
     * supplier chart never reads like a status chart by accident.
     */
    public const SERIES = [
        '#0b7677', '#43bdb8', '#1d4ed8', '#7c3aed', '#0891b2',
        '#be185d', '#4338ca', '#0f766e', '#6d28d9', '#155e75',
    ];

    /** Everything past the coloured slices is drawn as one grey band. */
    public const OTHER_COLOR = '#94a3b8';

    /** Suppliers drawn in their own colour before the rest becomes "Other". */
    public const COLORED_SLICES = 10;

    /**
     * @return array<int, array{
     *     name: string, supplier_id: ?int, purchases: int, spend: float,
     *     first_at: string, last_at: string, rank: int, anchor: string,
     *     share: float, average: float, color: string,
     * }>
     */
    public function summarize(Builder $query): array
    {
        $rows = (clone $query)
            ->selectRaw('supplier_id, supplier_name, COUNT(*) AS purchases, SUM(amount) AS spend, MIN(purchase_date) AS first_at, MAX(purchase_date) AS last_at')
            ->groupBy('supplier_id', 'supplier_name')
            ->get();

        $names = Supplier::whereIn('id', $rows->pluck('supplier_id')->filter()->unique())
            ->pluck('name', 'id');

        $merged = [];

        foreach ($rows as $row) {
            $name = $row->supplier_id
                ? ($names[$row->supplier_id] ?? trim((string) $row->supplier_name))
                : trim((string) $row->supplier_name);

            $name = $name !== '' ? $name : 'Unspecified supplier';
            $key  = mb_strtolower($name);

            $merged[$key] ??= [
                'name'        => $name,
                'supplier_id' => $row->supplier_id,
                'purchases'   => 0,
                'spend'       => 0.0,
                'first_at'    => (string) $row->first_at,
                'last_at'     => (string) $row->last_at,
            ];

            $merged[$key]['purchases'] += (int) $row->purchases;
            $merged[$key]['spend']     += (float) $row->spend;
            $merged[$key]['first_at']  = min($merged[$key]['first_at'], (string) $row->first_at);
            $merged[$key]['last_at']   = max($merged[$key]['last_at'], (string) $row->last_at);

            // A capture can name a Supplier that has since been merged into
            // another one (DuplicateProductService-style cleanup does not
            // touch suppliers today, but a manually re-pointed record would
            // land two different supplier_ids under the same name) — keep
            // whichever id this name has already been seen with, so a click
            // to filter always lands on the id the rest of the rows use too.
            $merged[$key]['supplier_id'] ??= $row->supplier_id;
        }

        $total = array_sum(array_column($merged, 'spend'));

        return collect($merged)
            ->sortByDesc('spend')
            ->values()
            ->map(function (array $row, int $i) use ($total) {
                $row['rank']    = $i + 1;
                $row['anchor']  = 'supplier-' . ($i + 1);
                $row['share']   = $total > 0 ? ($row['spend'] / $total) * 100 : 0.0;
                $row['average'] = $row['purchases'] > 0 ? $row['spend'] / $row['purchases'] : 0.0;
                $row['color']   = $i < self::COLORED_SLICES ? self::SERIES[$i] : self::OTHER_COLOR;

                return $row;
            })
            ->all();
    }
}
