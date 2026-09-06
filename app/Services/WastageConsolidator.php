<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\UnitOfMeasure;
use App\Models\WastageRecord;
use Illuminate\Support\Collection;

/**
 * Several wastage notes, one loss report.
 *
 * The Wastage Summary export answers "which department threw out the most".
 * This answers the question underneath it: exactly what got thrown out, and
 * how much of it, added up across every note in the range — the same shape
 * StockTakeConsolidator gives a range of counts, applied to loss instead of
 * stock on hand.
 *
 * A wastage line names either an ingredient or a recipe (a prepped batch
 * thrown out whole), so both are merged here the way
 * CostSummaryService::getItemDetail() already does for the Reports tab —
 * ingredients keep their catalogue category, recipes land in one "Recipes"
 * group. Quantities convert through UomService the same way
 * StockTakeConsolidator does, so grams logged in one note and kilograms in
 * another add up correctly; the rate shown is the value-weighted average,
 * not whichever note was read last.
 */
class WastageConsolidator
{
    public function __construct(private UomService $uom)
    {
    }

    /**
     * @param  Collection<int, WastageRecord>  $wastageRecords  notes to merge, lines loaded
     * @return array{
     *     groups: array<int, array{name: string, items: array<int, array<string, mixed>>, value: float}>,
     *     total: float,
     *     itemCount: int,
     *     records: Collection<int, WastageRecord>,
     * }
     */
    public function consolidate(Collection $wastageRecords): array
    {
        $ingredients = $this->ingredientsFor($wastageRecords);
        $recipes     = $this->recipesFor($wastageRecords);
        $uoms        = UnitOfMeasure::all()->keyBy('id');

        $items = [];

        foreach ($wastageRecords as $record) {
            foreach ($record->lines as $line) {
                if ($line->ingredient_id) {
                    $ingredient = $ingredients->get($line->ingredient_id);
                    if (! $ingredient) {
                        continue;   // the note outlived the item; nothing to file it under
                    }

                    $key      = 'ingredient:' . $line->ingredient_id;
                    $name     = $ingredient->name;
                    $code     = $ingredient->code;
                    $category = $this->categoryName($ingredient);
                    $default  = $ingredient->recipeUom ?: $ingredient->baseUom;
                    $ingredientId = (int) $line->ingredient_id;
                } elseif ($line->recipe_id) {
                    $recipe = $recipes->get($line->recipe_id);
                    if (! $recipe) {
                        continue;
                    }

                    $key      = 'recipe:' . $line->recipe_id;
                    $name     = $recipe->name;
                    $code     = null;
                    $category = 'Recipes';
                    $default  = $recipe->yieldUom;
                    $ingredientId = null;
                } else {
                    continue;   // neither — nothing to merge this line under
                }

                $quantity = (float) $line->quantity;
                $value    = $quantity * (float) $line->unit_cost;

                // One unit per item, chosen once: the unit this item is
                // logged in. Whatever a given note used is converted into it.
                $target = $items[$key]['uom'] ?? $default;
                $source = $uoms->get((int) $line->uom_id) ?: $target;

                if ($target && $source) {
                    $quantity = $this->uom->convertQuantity($quantity, $source, $target, $ingredientId);
                }

                if (! isset($items[$key])) {
                    $items[$key] = [
                        'name'     => $name,
                        'code'     => $code,
                        'uom'      => $target,
                        'uom_abbr' => $target?->abbreviation ?? '',
                        'quantity' => 0.0,
                        'value'    => 0.0,
                        'lines'    => 0,
                        'category' => $category,
                        'reasons'  => [],
                    ];
                }

                $items[$key]['quantity'] += $quantity;
                $items[$key]['value']    += $value;
                $items[$key]['lines']++;

                if ($line->reason) {
                    $items[$key]['reasons'][trim($line->reason)] = true;
                }
            }
        }

        return [
            'groups'    => $this->group($items),
            'total'     => round(array_sum(array_column($items, 'value')), 2),
            'itemCount' => count($items),
            'records'   => $wastageRecords,
        ];
    }

    /**
     * The rate is derived, not carried: two notes at different prices make one
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
            $item['reasons']   = array_keys($item['reasons']);

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

        // Recipes, then Uncategorised last; both are a step away from the
        // ingredient catalogue proper, not a gap in it.
        uksort($groups, function ($a, $b) {
            foreach (['Uncategorized', 'Recipes'] as $last) {
                if ($a === $last && $b === $last) return 0;
                if ($a === $last) return 1;
                if ($b === $last) return -1;
            }
            return strcmp($a, $b);
        });

        return array_values($groups);
    }

    private function categoryName(Ingredient $ingredient): string
    {
        $category = $ingredient->ingredientCategory;

        return $category?->parent?->name ?? $category?->name ?? 'Uncategorized';
    }

    /** @param Collection<int, WastageRecord> $wastageRecords */
    private function ingredientsFor(Collection $wastageRecords): Collection
    {
        $ids = $wastageRecords->pluck('lines')->flatten()->pluck('ingredient_id')->filter()->unique();

        return Ingredient::withTrashed()
            ->with(['baseUom', 'recipeUom', 'ingredientCategory.parent'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /** @param Collection<int, WastageRecord> $wastageRecords */
    private function recipesFor(Collection $wastageRecords): Collection
    {
        $ids = $wastageRecords->pluck('lines')->flatten()->pluck('recipe_id')->filter()->unique();

        return Recipe::withTrashed()
            ->with(['yieldUom'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }
}
