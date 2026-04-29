<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientUomConversion;
use App\Models\UnitOfMeasure;

class UomService
{
    /**
     * Convert an ingredient's cost to cost per target UOM.
     * Note: current_cost is stored as cost per RECIPE UOM (purchase_price / pack_size).
     * Tries ingredient-specific conversions first, then falls back to standard UOM factor ratio.
     */
    public function convertCost(Ingredient $ingredient, UnitOfMeasure $targetUom): float
    {
        $baseUom = $ingredient->baseUom;

        // If target is the recipe UOM, check for base → recipe conversion first
        // (handles cases where pack_size=1 but a UOM conversion exists)
        if ($ingredient->recipe_uom_id && (int) $targetUom->id === (int) $ingredient->recipe_uom_id) {
            $baseId = (int) $baseUom->id;
            $recipeId = (int) $ingredient->recipe_uom_id;

            // If base == recipe, current_cost is already correct
            if ($baseId === $recipeId) {
                return (float) $ingredient->current_cost;
            }

            // Look for base → recipe conversion (e.g. ctn → ml = 12000)
            if ($ingredient->relationLoaded('uomConversions')) {
                $baseToRecipe = $ingredient->uomConversions->first(
                    fn ($c) => (int) $c->from_uom_id === $baseId && (int) $c->to_uom_id === $recipeId
                );
            } else {
                $baseToRecipe = IngredientUomConversion::where('ingredient_id', $ingredient->id)
                    ->where('from_uom_id', $baseId)
                    ->where('to_uom_id', $recipeId)
                    ->first();
            }

            // If conversion exists, use it: cost per recipe = purchase_price / conversion_factor
            if ($baseToRecipe && (float) $baseToRecipe->factor > 0) {
                return (float) $ingredient->purchase_price / (float) $baseToRecipe->factor;
            }

            // Otherwise use current_cost (assumes pack_size is correct)
            return (float) $ingredient->current_cost;
        }

        // If target is the base UOM, return purchase_price (cost per base UOM)
        if ($baseUom->id === $targetUom->id) {
            // If base == recipe, current_cost is already correct
            if ((int) $baseUom->id === (int) $ingredient->recipe_uom_id) {
                return (float) $ingredient->current_cost;
            }
            // Otherwise, purchase_price is the cost per base UOM
            return (float) $ingredient->purchase_price;
        }

        $baseId   = (int) $baseUom->id;
        $targetId = (int) $targetUom->id;

        // Check ingredient-specific conversion (use loaded relation if available to avoid N+1)
        if ($ingredient->relationLoaded('uomConversions')) {
            $conversion        = $ingredient->uomConversions->first(
                fn ($c) => (int) $c->from_uom_id === $baseId && (int) $c->to_uom_id === $targetId
            );
            $reverseConversion = $ingredient->uomConversions->first(
                fn ($c) => (int) $c->from_uom_id === $targetId && (int) $c->to_uom_id === $baseId
            );
        } else {
            $conversion = IngredientUomConversion::where('ingredient_id', $ingredient->id)
                ->where('from_uom_id', $baseUom->id)
                ->where('to_uom_id', $targetUom->id)
                ->first();
            $reverseConversion = IngredientUomConversion::where('ingredient_id', $ingredient->id)
                ->where('from_uom_id', $targetUom->id)
                ->where('to_uom_id', $baseUom->id)
                ->first();
        }

        // from=base, to=target, factor=N means "1 base = N target"
        // cost per target = purchase_price ÷ N  (e.g. RM60/ctn ÷ 12000ml/ctn = RM0.005/ml)
        if ($conversion && (float) $conversion->factor != 0) {
            return (float) $ingredient->purchase_price / (float) $conversion->factor;
        }

        // reverse: from=target, to=base, factor=N means "1 target = N base"
        // cost per target = purchase_price × N  (e.g. RM60/ctn × 0.001ctn/ml = RM0.06/ml)
        if ($reverseConversion && (float) $reverseConversion->factor != 0) {
            return (float) $ingredient->purchase_price * (float) $reverseConversion->factor;
        }

        // Chain through recipe UOM for secondary recipe UOM resolution.
        // The secondary conversion is stored as: target → recipe, factor = N
        // meaning "1 [secondary/target] = N [recipe_uom]".
        // e.g. Recipe=G, Secondary=bsp, factor=27 → stored as bsp→G, factor=27.
        //   1. cost per G   = convertCost(ingredient, G)    [direct or standard-factor]
        //   2. conversion bsp→G factor=27  →  1 bsp = 27 G
        //   3. cost per bsp = cost per G × 27
        // This chain fires when base ≠ recipe (e.g. base=KG, recipe=G) so the direct
        // lookups above (base↔target) don't find the bsp row.
        if ($ingredient->recipe_uom_id && (int) $ingredient->recipe_uom_id !== $baseId) {
            $recipeId = (int) $ingredient->recipe_uom_id;

            // Look for target → recipe conversion (1 target = N recipe)
            if ($ingredient->relationLoaded('uomConversions')) {
                $targetToRecipe = $ingredient->uomConversions->first(
                    fn ($c) => (int) $c->from_uom_id === $targetId && (int) $c->to_uom_id === $recipeId
                );
            } else {
                $targetToRecipe = IngredientUomConversion::where('ingredient_id', $ingredient->id)
                    ->where('from_uom_id', $targetId)
                    ->where('to_uom_id', $recipeId)
                    ->first();
            }

            if ($targetToRecipe && (float) $targetToRecipe->factor > 0) {
                $recipeUom = $targetUom->newQuery()->find($recipeId);
                if ($recipeUom) {
                    $costPerRecipe = $this->convertCost($ingredient, $recipeUom);
                    // 1 target = factor recipe-units, so cost per target = cost per recipe × factor
                    return $costPerRecipe * (float) $targetToRecipe->factor;
                }
            }
        }

        // Fall back to standard UOM base_unit_factor ratio.
        // base_unit_factor = "how many base-SI units in 1 of this UOM" (e.g. g=0.001, kg=1.0)
        // cost per target = cost per base × (base_factor / target_factor) is WRONG for cost.
        // Correct: 1 kg = 1000 g, so RM30/kg ÷ 1000 = RM0.03/g
        //          factor = targetUom->factor / baseUom->factor = 0.001/1.0 = 0.001
        if ($baseUom->base_unit_factor && $targetUom->base_unit_factor && $baseUom->base_unit_factor != 0) {
            $factor = (float) $targetUom->base_unit_factor / (float) $baseUom->base_unit_factor;
            return (float) $ingredient->current_cost * $factor;
        }

        return (float) $ingredient->current_cost;
    }

    /**
     * Convert a quantity from one UOM to another using ingredient-specific or standard conversions.
     */
    public function convertQuantity(float $quantity, UnitOfMeasure $fromUom, UnitOfMeasure $toUom, ?int $ingredientId = null): float
    {
        if ($fromUom->id === $toUom->id) {
            return $quantity;
        }

        if ($ingredientId) {
            $conversion = IngredientUomConversion::where('ingredient_id', $ingredientId)
                ->where('from_uom_id', $fromUom->id)
                ->where('to_uom_id', $toUom->id)
                ->first();

            if ($conversion) {
                return $quantity * (float) $conversion->factor;
            }
        }

        // Standard factor-based conversion
        if ($fromUom->base_unit_factor && $toUom->base_unit_factor && $toUom->base_unit_factor != 0) {
            return $quantity * ((float) $fromUom->base_unit_factor / (float) $toUom->base_unit_factor);
        }

        return $quantity;
    }
}
