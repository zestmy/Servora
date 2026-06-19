<?php

namespace App\Services;

use App\Models\Recipe;

/**
 * Keeps the denormalised cost of prep items in sync with the live cost of the
 * ingredients they are built from.
 *
 * Regular recipes recompute their cost on the fly (Recipe::getTotalCostAttribute),
 * so they always reflect the latest ingredient prices. Prep items are different:
 * a prep item is a Recipe (is_prep = true) plus a synced Ingredient
 * (prep_recipe_id) whose `current_cost` is a STORED value. That stored cost is
 * consumed verbatim by UomService::convertCost() whenever the prep item is used
 * as a line in another recipe or prep. If it is not refreshed when an underlying
 * ingredient price changes, every recipe/prep that uses the prep item silently
 * costs from a stale figure.
 *
 * This service recomputes a prep item's cost from its lines (mirroring exactly
 * how PrepItemForm::computeLineCosts() does it, so a recalculation produces the
 * same number as a manual re-save) and cascades the change through any prep items
 * that depend on it, however deep the chain goes.
 */
class PrepCostService
{
    /** Treat cost differences below this as "unchanged" (current_cost is stored to 4 dp). */
    private const EPSILON = 0.00005;

    /**
     * Recompute every prep item that uses any of the given ingredients, then
     * cascade to prep items that use those prep items, and so on.
     *
     * @param  array<int>  $ingredientIds  Ingredients whose cost just changed.
     * @return array<int>  IDs of prep-item ingredients whose stored cost changed.
     */
    public function recalculateForIngredients(array $ingredientIds): array
    {
        $queue = array_values(array_unique(array_filter(array_map('intval', $ingredientIds))));
        $changed = [];

        // Backstop against a pathological prep dependency cycle. Convergence
        // (epsilon comparison below) normally empties the queue well before this.
        $guard = 0;
        $guardLimit = 5000;

        while (! empty($queue) && $guard < $guardLimit) {
            $guard++;
            $ingredientId = array_shift($queue);

            $prepRecipes = Recipe::query()
                ->where('is_prep', true)
                ->whereHas('lines', fn ($q) => $q->where('ingredient_id', $ingredientId))
                ->with([
                    'lines.ingredient.baseUom',
                    'lines.ingredient.recipeUom',
                    'lines.ingredient.uomConversions',
                    'lines.uom',
                    'ingredient',
                ])
                ->get();

            foreach ($prepRecipes as $recipe) {
                $changedPrepIngredientId = $this->recalculatePrep($recipe);
                if ($changedPrepIngredientId !== null) {
                    $changed[] = $changedPrepIngredientId;
                    $queue[] = $changedPrepIngredientId; // cascade to consumers of this prep
                }
            }
        }

        return array_values(array_unique($changed));
    }

    /**
     * Recompute the stored cost of every prep item (all companies). Intended for
     * one-off backfills of historically stale data. Runs repeated passes so deep
     * prep → prep → prep chains converge regardless of processing order.
     *
     * @return int  Number of prep items whose stored cost was updated.
     */
    public function recalculateAll(): int
    {
        $updated = 0;
        $maxPasses = 50;

        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $changedThisPass = 0;

            Recipe::query()
                ->where('is_prep', true)
                ->with([
                    'lines.ingredient.baseUom',
                    'lines.ingredient.recipeUom',
                    'lines.ingredient.uomConversions',
                    'lines.uom',
                    'ingredient',
                ])
                ->chunkById(200, function ($recipes) use (&$changedThisPass) {
                    foreach ($recipes as $recipe) {
                        if ($this->recalculatePrep($recipe) !== null) {
                            $changedThisPass++;
                        }
                    }
                });

            $updated += $changedThisPass;
            if ($changedThisPass === 0) {
                break; // fixed point reached
            }
        }

        return $updated;
    }

    /**
     * Recompute and persist one prep item's cost.
     *
     * Writes use saveQuietly() so the IngredientObserver is not re-entered — the
     * cascade is driven by this service's own queue, not by nested model events.
     *
     * @return int|null  The prep-item ingredient id if its stored cost changed, else null.
     */
    private function recalculatePrep(Recipe $recipe): ?int
    {
        $costPerYield = round($this->computeCostPerYield($recipe), 4);

        // Recipe::getCostPerYieldUnitAttribute() shadows this column on read, so
        // compare against the raw stored value, not $recipe->cost_per_yield_unit.
        $storedRecipeCost = (float) $recipe->getRawOriginal('cost_per_yield_unit');
        if (abs($storedRecipeCost - $costPerYield) >= self::EPSILON) {
            $recipe->forceFill(['cost_per_yield_unit' => $costPerYield])->saveQuietly();
        }

        $prepIngredient = $recipe->ingredient;
        if (! $prepIngredient) {
            return null;
        }

        if (abs((float) $prepIngredient->current_cost - $costPerYield) < self::EPSILON) {
            return null;
        }

        $prepIngredient->forceFill(['current_cost' => $costPerYield])->saveQuietly();

        return (int) $prepIngredient->id;
    }

    /**
     * Cost of one yield unit of a prep recipe, computed from its lines.
     *
     * This mirrors PrepItemForm::computeLineCosts() exactly:
     *  - prep-item lines use their stored current_cost (purchase_price is always 0);
     *  - regular-ingredient lines use the PRE-yield cost (purchase_price / pack_size),
     *    temporarily overriding current_cost so UomService applies pack/UOM conversions
     *    without the yield adjustment.
     */
    private function computeCostPerYield(Recipe $recipe): float
    {
        $uomService = app(UomService::class);
        $total = 0.0;

        foreach ($recipe->lines as $line) {
            $ingredient = $line->ingredient;
            $uom = $line->uom;
            $qty = (float) $line->quantity;

            if (! $ingredient || ! $uom || $qty <= 0) {
                continue;
            }

            if ($ingredient->is_prep) {
                $costPerUom = $uomService->convertCost($ingredient, $uom);
            } else {
                $originalCost = $ingredient->current_cost;
                $packSize = max((float) $ingredient->pack_size, 0.0001);
                $ingredient->current_cost = (float) $ingredient->purchase_price / $packSize;
                $costPerUom = $uomService->convertCost($ingredient, $uom);
                $ingredient->current_cost = $originalCost;
            }

            $wasteFactor = 1 + ((float) $line->waste_percentage / 100);
            $total += $costPerUom * $wasteFactor * $qty;
        }

        $yield = max((float) $recipe->yield_quantity, 0.0001);

        return $total / $yield;
    }
}
