<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientUomConversion;
use App\Models\UnitOfMeasure;

class UomService
{
    /**
     * Convert an ingredient's current_cost (stored in base_uom) to cost per recipe_uom.
     * Tries ingredient-specific conversions first, then falls back to standard UOM factor ratio.
     */
    public function convertCost(Ingredient $ingredient, UnitOfMeasure $targetUom): float
    {
        $baseUom = $ingredient->baseUom;

        if ($baseUom->id === $targetUom->id) {
            return (float) $ingredient->current_cost;
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
        // cost per target = cost per base ÷ N  (e.g. RM60/kg ÷ 30pcs/kg = RM2/pcs)
        if ($conversion && (float) $conversion->factor != 0) {
            return (float) $ingredient->current_cost / (float) $conversion->factor;
        }

        if ($reverseConversion) {
            return (float) $ingredient->current_cost * (float) $reverseConversion->factor;
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
