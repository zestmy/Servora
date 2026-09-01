<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\StockTake;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Collection;

/**
 * Several counts, one inventory.
 *
 * A kitchen does not count itself in one pass — it counts the saute chiller,
 * then the bread freezer, then the bar, each as its own sheet. For a month-end
 * file what is wanted is the other shape: one list of what the outlet holds,
 * with each item appearing once however many sheets it turned up on.
 *
 * Two things make that more than a SUM(). The same ingredient can be counted in
 * different units on different sheets — grams in prep, kilograms in the store —
 * so quantities are converted to one unit per item before they are added. And
 * the sheets can carry different unit costs, if prices moved between counts, so
 * the rate shown is the weighted average the value actually implies rather than
 * whichever sheet happened to be read last.
 */
class StockTakeConsolidator
{
    public function __construct(private UomService $uom)
    {
    }

    /**
     * @param  Collection<int, StockTake>  $stockTakes  counts to merge, lines loaded
     * @return array{
     *     groups: array<int, array{name: string, items: array<int, array<string, mixed>>, value: float}>,
     *     total: float,
     *     itemCount: int,
     *     takes: Collection<int, StockTake>,
     *     draftCount: int
     * }
     */
    public function consolidate(Collection $stockTakes): array
    {
        $ingredients = $this->ingredientsFor($stockTakes);
        $uoms        = UnitOfMeasure::all()->keyBy('id');   // small table, one query

        $items = [];

        foreach ($stockTakes as $take) {
            foreach ($take->lines as $line) {
                $ingredient = $ingredients->get($line->ingredient_id);

                if (! $ingredient) {
                    continue;   // the count outlived the item; nothing to file it under
                }

                $key      = (int) $line->ingredient_id;
                $quantity = (float) $line->actual_quantity;
                $value    = $quantity * (float) $line->unit_cost;

                // One unit per item, chosen once: the unit this item is counted
                // in. Whatever a given sheet used is converted into it.
                $target = $items[$key]['uom'] ?? ($ingredient->recipeUom ?: $ingredient->baseUom);
                $source = $uoms->get((int) $line->uom_id) ?: $target;

                if ($target && $source) {
                    $quantity = $this->uom->convertQuantity($quantity, $source, $target, $ingredient->id);
                }

                if (! isset($items[$key])) {
                    $items[$key] = [
                        'ingredient' => $ingredient,
                        'name'       => $ingredient->name,
                        'code'       => $ingredient->code,
                        'uom'        => $target,
                        'uom_abbr'   => $target?->abbreviation ?? '',
                        'quantity'   => 0.0,
                        'value'      => 0.0,
                        'sheets'     => 0,
                        'category'   => $this->categoryName($ingredient),
                    ];
                }

                $items[$key]['quantity'] += $quantity;
                $items[$key]['value']    += $value;
                $items[$key]['sheets']++;
            }
        }

        return [
            'groups'     => $this->group($items),
            'total'      => round(array_sum(array_column($items, 'value')), 2),
            'itemCount'  => count($items),
            'takes'      => $stockTakes,
            'draftCount' => $stockTakes->where('status', '!=', 'completed')->count(),
        ];
    }

    /**
     * The rate is derived, not carried: two sheets at different prices make one
     * line whose unit cost is whatever its value divided by its quantity is.
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

            unset($item['ingredient'], $item['uom']);

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

    /** @param Collection<int, StockTake> $stockTakes */
    private function ingredientsFor(Collection $stockTakes): Collection
    {
        $ids = $stockTakes->pluck('lines')->flatten()->pluck('ingredient_id')->filter()->unique();

        return Ingredient::withTrashed()
            ->with(['baseUom', 'recipeUom', 'ingredientCategory.parent'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }
}
