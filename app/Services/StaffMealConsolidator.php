<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\StaffMealRecord;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Collection;

/**
 * Several staff meal entries, one serving report.
 *
 * The Staff Meal Summary export answers "which outlet fed staff the most".
 * This answers the question underneath it: exactly what was served, and how
 * much of it, added up across every entry in the range — the same shape
 * WastageConsolidator gives a range of wastage notes, applied to what staff
 * ate instead of what was thrown out.
 *
 * A staff meal line names either an ingredient or a recipe (a prepped dish
 * served whole), merged the way CostSummaryService::getItemDetail() already
 * does for the Reports tab — ingredients keep their catalogue category,
 * recipes land in one "Recipes" group. Quantities convert through UomService
 * the same way WastageConsolidator does, so a mixed-unit range still adds up
 * correctly; the rate shown is the value-weighted average.
 */
class StaffMealConsolidator
{
    public function __construct(private UomService $uom)
    {
    }

    /**
     * @param  Collection<int, StaffMealRecord>  $records  entries to merge, lines loaded
     * @return array{
     *     groups: array<int, array{name: string, items: array<int, array<string, mixed>>, value: float}>,
     *     total: float,
     *     itemCount: int,
     *     records: Collection<int, StaffMealRecord>,
     * }
     */
    public function consolidate(Collection $records): array
    {
        $ingredients = $this->ingredientsFor($records);
        $recipes     = $this->recipesFor($records);
        $uoms        = UnitOfMeasure::all()->keyBy('id');

        $items = [];

        foreach ($records as $record) {
            foreach ($record->lines as $line) {
                if ($line->ingredient_id) {
                    $ingredient = $ingredients->get($line->ingredient_id);
                    if (! $ingredient) {
                        continue;   // the entry outlived the item; nothing to file it under
                    }

                    $key          = 'ingredient:' . $line->ingredient_id;
                    $name         = $ingredient->name;
                    $code         = $ingredient->code;
                    $category     = $this->categoryName($ingredient);
                    $default      = $ingredient->recipeUom ?: $ingredient->baseUom;
                    $ingredientId = (int) $line->ingredient_id;
                } elseif ($line->recipe_id) {
                    $recipe = $recipes->get($line->recipe_id);
                    if (! $recipe) {
                        continue;
                    }

                    $key          = 'recipe:' . $line->recipe_id;
                    $name         = $recipe->name;
                    $code         = null;
                    $category     = 'Recipes';
                    $default      = $recipe->yieldUom;
                    $ingredientId = null;
                } else {
                    continue;   // neither — nothing to merge this line under
                }

                $quantity = (float) $line->quantity;
                $value    = $quantity * (float) $line->unit_cost;

                // One unit per item, chosen once: the unit this item is
                // logged in. Whatever a given entry used is converted into it.
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
            'records'   => $records,
        ];
    }

    /**
     * The rate is derived, not carried: two entries at different prices make
     * one line whose unit cost is whatever its value divided by its quantity is.
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

    /** @param Collection<int, StaffMealRecord> $records */
    private function ingredientsFor(Collection $records): Collection
    {
        $ids = $records->pluck('lines')->flatten()->pluck('ingredient_id')->filter()->unique();

        return Ingredient::withTrashed()
            ->with(['baseUom', 'recipeUom', 'ingredientCategory.parent'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /** @param Collection<int, StaffMealRecord> $records */
    private function recipesFor(Collection $records): Collection
    {
        $ids = $records->pluck('lines')->flatten()->pluck('recipe_id')->filter()->unique();

        return Recipe::withTrashed()
            ->with(['yieldUom'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }
}
