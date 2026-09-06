<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\OutletTransfer;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Collection;

/**
 * Several transfers, one movement report.
 *
 * The Transfer Summary export answers "which outlet sent the most value".
 * This answers the question underneath it: exactly what moved, and how much
 * of it, added up across every transfer in the range — the same shape
 * WastageConsolidator gives a range of wastage notes, applied to stock moved
 * between outlets instead of stock thrown out.
 *
 * A transfer line only ever names an ingredient (OutletTransferLine has no
 * recipe_id — a transfer moves raw stock, not a prepped dish) and carries no
 * stored total_cost of its own, so the value is quantity × unit_cost, summed
 * here the same way TransferSummaryController sums it for the chart and the
 * summary export. Quantities convert through UomService the same way
 * WastageConsolidator does, so a mixed-unit range still adds up correctly.
 */
class TransferConsolidator
{
    public function __construct(private UomService $uom)
    {
    }

    /**
     * @param  Collection<int, OutletTransfer>  $transfers  transfers to merge, lines loaded
     * @return array{
     *     groups: array<int, array{name: string, items: array<int, array<string, mixed>>, value: float}>,
     *     total: float,
     *     itemCount: int,
     *     transfers: Collection<int, OutletTransfer>,
     * }
     */
    public function consolidate(Collection $transfers): array
    {
        $ingredients = $this->ingredientsFor($transfers);
        $uoms        = UnitOfMeasure::all()->keyBy('id');

        $items = [];

        foreach ($transfers as $transfer) {
            foreach ($transfer->lines as $line) {
                $ingredient = $ingredients->get($line->ingredient_id);
                if (! $ingredient) {
                    continue;   // the transfer outlived the item; nothing to file it under
                }

                $key      = (int) $line->ingredient_id;
                $quantity = (float) $line->quantity;
                $value    = $quantity * (float) $line->unit_cost;

                // One unit per item, chosen once: the unit this item is
                // moved in. Whatever a given transfer used is converted into it.
                $target = $items[$key]['uom'] ?? ($ingredient->recipeUom ?: $ingredient->baseUom);
                $source = $uoms->get((int) $line->uom_id) ?: $target;

                if ($target && $source) {
                    $quantity = $this->uom->convertQuantity($quantity, $source, $target, $ingredient->id);
                }

                if (! isset($items[$key])) {
                    $items[$key] = [
                        'name'     => $ingredient->name,
                        'code'     => $ingredient->code,
                        'uom'      => $target,
                        'uom_abbr' => $target?->abbreviation ?? '',
                        'quantity' => 0.0,
                        'value'    => 0.0,
                        'lines'    => 0,
                        'category' => $this->categoryName($ingredient),
                    ];
                }

                $items[$key]['quantity'] += $quantity;
                $items[$key]['value']    += $value;
                $items[$key]['lines']++;
            }
        }

        return [
            'groups'    => $this->group($items),
            'total'     => round(array_sum(array_column($items, 'value')), 2),
            'itemCount' => count($items),
            'transfers' => $transfers,
        ];
    }

    /**
     * The rate is derived, not carried: two transfers at different prices
     * make one line whose unit cost is whatever its value divided by its
     * quantity is.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{name: string, items: array<int, array<string, mixed>>, value: float}>
     */
    private function group(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $item['quantity']  = round($item['quantity'], 4);
            $item['value']     = round($item['value'], 2);
            $item['unit_cost'] = $item['quantity'] > 0
                ? round($item['value'] / $item['quantity'], 4)
                : 0.0;

            unset($item['uom']);

            $groups[$item['category']]['name'] ??= $item['category'];
            $groups[$item['category']]['items'][] = $item;
            $groups[$item['category']]['value'] = ($groups[$item['category']]['value'] ?? 0) + $item['value'];
        }

        foreach ($groups as $name => $group) {
            usort($group['items'], fn ($a, $b) => strcmp($a['name'], $b['name']));
            $groups[$name]['items'] = $group['items'];
            $groups[$name]['value'] = round($group['value'], 2);
        }

        // Uncategorised last; it is a gap in the catalogue, not a category.
        uksort($groups, function ($a, $b) {
            if ($a === 'Uncategorized') return 1;
            if ($b === 'Uncategorized') return -1;
            return strcmp($a, $b);
        });

        return array_values($groups);
    }

    private function categoryName(Ingredient $ingredient): string
    {
        $category = $ingredient->ingredientCategory;

        return $category?->parent?->name ?? $category?->name ?? 'Uncategorized';
    }

    /** @param Collection<int, OutletTransfer> $transfers */
    private function ingredientsFor(Collection $transfers): Collection
    {
        $ids = $transfers->pluck('lines')->flatten()->pluck('ingredient_id')->filter()->unique();

        return Ingredient::withTrashed()
            ->with(['baseUom', 'recipeUom', 'ingredientCategory.parent'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }
}
