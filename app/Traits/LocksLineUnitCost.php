<?php

namespace App\Traits;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\UnitOfMeasure;
use App\Services\UomService;
use Livewire\Attributes\Locked;

/**
 * Unit cost on a stock form is shown, never entered.
 *
 * Prices belong to purchasing — a goods-received note, a supplier invoice, an
 * ingested bill, an ingredient price list. A stock take, a wastage note, a transfer and a staff meal all
 * spend a price that someone else set; letting a counter retype it makes the
 * same item cost two different things depending on which screen recorded it,
 * and quietly moves stock value without an invoice behind it.
 *
 * Making the input readonly stops a person typing over it and stops nothing
 * else: `lines` is a public Livewire property, so the browser can still put any
 * number on the wire. So the cost the server writes never comes from the
 * submitted row — it comes from this map, which is #[Locked] and therefore
 * refuses client updates outright.
 *
 * Keyed by item identity rather than row index: rows get reordered, inserted
 * and removed, and an index-keyed map would hand a price to the wrong item.
 */
trait LocksLineUnitCost
{
    /** @var array<string, float> item key => unit cost, server-authored only */
    #[Locked]
    public array $lineCosts = [];

    protected function lineCostKey(array $line): string
    {
        if (($line['item_type'] ?? 'ingredient') === 'recipe') {
            return 'recipe:' . (int) ($line['recipe_id'] ?? 0);
        }

        // The UOM is part of the identity: the same ingredient counted in
        // grams and in kilograms is not the same price.
        return 'ingredient:' . (int) ($line['ingredient_id'] ?? 0)
             . ':uom:' . (int) ($line['uom_id'] ?? 0);
    }

    /**
     * Record the cost the server worked out, and mirror it onto the row for
     * display. Returns the row so a builder can hand it straight to $lines.
     */
    protected function rememberLineCost(array $line, float $cost): array
    {
        $cost = round($cost, 4);

        $this->lineCosts[$this->lineCostKey($line)] = $cost;
        $line['unit_cost'] = (string) $cost;

        return $line;
    }

    /**
     * The cost to store for a row.
     *
     * The map is the fast path and the one that preserves what a saved record
     * was originally priced at. A miss falls through to a fresh look-up, never
     * to the submitted value: `uom_id` is part of the key and is itself a
     * public array member, so trusting the row here would let a crafted
     * request shift the key and smuggle its own price in through the gap.
     */
    protected function lockedLineCost(array $line): float
    {
        $key = $this->lineCostKey($line);

        if (isset($this->lineCosts[$key])) {
            return (float) $this->lineCosts[$key];
        }

        return $this->deriveLineCost($line);
    }

    /** Price a row from its source of record, ignoring whatever it arrived carrying. */
    protected function deriveLineCost(array $line): float
    {
        if (($line['item_type'] ?? 'ingredient') === 'recipe') {
            $recipe = Recipe::find($line['recipe_id'] ?? null);

            return $recipe ? round((float) $recipe->cost_per_yield_unit, 4) : 0.0;
        }

        $ingredient = Ingredient::with(['baseUom', 'recipeUom', 'uomConversions'])
            ->find($line['ingredient_id'] ?? null);

        if (! $ingredient) {
            return 0.0;
        }

        // The row's own UOM, so a stock take priced per gram stays per gram.
        $uom = UnitOfMeasure::find($line['uom_id'] ?? null)
            ?: ($ingredient->recipeUom ?: $ingredient->baseUom);

        return $uom
            ? round(app(UomService::class)->convertCost($ingredient, $uom), 4)
            : round((float) $ingredient->current_cost, 4);
    }
}
