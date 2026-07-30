<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\LabelPrint;
use App\Models\ProductionRecipe;
use App\Models\Recipe;
use App\Models\WastageRecord;
use App\Models\WastageRecordLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Closing off printed labels: used, binned and costed, or binned and not
 * costable.
 *
 * Costing is the awkward part. wastage_record_lines requires quantity,
 * uom_id, unit_cost and total_cost, all NOT NULL — but a label carries none
 * of them. So a label is only costable when it links to something priceable,
 * and the chef supplies the quantity at the point of binning it. Anything
 * else is recorded as 'discarded' rather than invented at zero cost, which
 * would quietly understate wastage.
 */
class LabelExpiryService
{
    public function __construct(private UomService $uom)
    {
    }

    /**
     * Costing basis for a label, or null when there is nothing to price.
     *
     * @return array{ingredient_id: ?int, recipe_id: ?int, uom_id: ?int, uom_abbr: string, unit_cost: float}|null
     */
    public function costingFor(LabelPrint $print): ?array
    {
        $item = $print->labelable;

        if (! $item) {
            return null;
        }

        // A production recipe isn't a wastage line type, but it mirrors an
        // ingredient — cost against that.
        if ($item instanceof ProductionRecipe) {
            $item = $item->ingredient;
        }

        $costing = match (true) {
            $item instanceof Ingredient => $this->ingredientCosting($item),
            $item instanceof Recipe     => $this->recipeCosting($item),
            default                     => null,
        };

        // uom_id is NOT NULL on wastage_record_lines. An item with no unit
        // of measure — a recipe with no yield UOM set — can't be costed, and
        // saying so here is better than a constraint violation at insert.
        return $costing && $costing['uom_id'] ? $costing : null;
    }

    /** Consumed normally. Nothing to cost, nothing to record beyond the fact. */
    public function markUsed(LabelPrint $print): void
    {
        $this->resolve($print, 'used');
    }

    /** Binned, but nothing priceable behind it. Deliberately NOT costed. */
    public function markDiscarded(LabelPrint $print): void
    {
        $this->resolve($print, 'discarded');
    }

    /**
     * Binned and costed.
     *
     * Lines are appended to a single wastage record per outlet per day
     * rather than one record per label — a chiller clear-out would
     * otherwise produce fifteen separate records and make the wastage
     * report unreadable.
     */
    public function markWasted(LabelPrint $print, float $quantity): ?WastageRecord
    {
        $costing = $this->costingFor($print);

        if (! $costing || $quantity <= 0) {
            // Refuse to guess. Better an uncosted 'discarded' than a wrong
            // number in the cost report.
            $this->markDiscarded($print);

            return null;
        }

        return DB::transaction(function () use ($print, $quantity, $costing) {
            $record = $this->dailyRecordFor($print);

            $totalCost = round($quantity * $costing['unit_cost'], 4);

            WastageRecordLine::create([
                'wastage_record_id' => $record->id,
                'ingredient_id'     => $costing['ingredient_id'],
                'recipe_id'         => $costing['recipe_id'],
                'quantity'          => $quantity,
                'uom_id'            => $costing['uom_id'],
                'unit_cost'         => $costing['unit_cost'],
                'total_cost'        => $totalCost,
                'reason'            => 'Expired — ' . $print->printedName(),
            ]);

            $record->update([
                'total_cost' => (float) $record->lines()->sum('total_cost'),
            ]);

            $this->resolve($print, 'wasted', $record->id);

            return $record;
        });
    }

    /**
     * One wastage record per outlet per day for label-driven wastage,
     * created on first use and appended to thereafter.
     */
    private function dailyRecordFor(LabelPrint $print): WastageRecord
    {
        $date = Carbon::today();

        $existing = WastageRecord::where('outlet_id', $print->outlet_id)
            ->whereDate('wastage_date', $date)
            ->where('reference_number', $this->referenceFor($print->outlet_id, $date))
            ->first();

        if ($existing) {
            return $existing;
        }

        return WastageRecord::create([
            'company_id'       => $print->company_id,
            'outlet_id'        => $print->outlet_id,
            'reference_number' => $this->referenceFor($print->outlet_id, $date),
            'wastage_date'     => $date,
            'total_cost'       => 0,
            'notes'            => 'Auto-created from expired food safety labels.',
            'created_by'       => Auth::id(),
        ]);
    }

    private function referenceFor(int $outletId, Carbon $date): string
    {
        return sprintf('WST-LBL-%s-%d', $date->format('Ymd'), $outletId);
    }

    private function resolve(LabelPrint $print, string $resolution, ?int $wastageRecordId = null): void
    {
        $print->update([
            'resolved_at'       => Carbon::now(),
            'resolved_by'       => Auth::id(),
            'resolution'        => $resolution,
            'wastage_record_id' => $wastageRecordId,
        ]);
    }

    /** Mirrors how the wastage form prices an ingredient line. */
    private function ingredientCosting(Ingredient $ingredient): array
    {
        $ingredient->loadMissing(['baseUom', 'recipeUom', 'uomConversions']);

        $countUom = $ingredient->recipeUom ?: $ingredient->baseUom;

        $unitCost = $countUom
            ? $this->uom->convertCost($ingredient, $countUom)
            : (float) $ingredient->current_cost;

        return [
            'ingredient_id' => $ingredient->id,
            'recipe_id'     => null,
            'uom_id'        => $countUom?->id ?? $ingredient->base_uom_id,
            'uom_abbr'      => $countUom?->abbreviation ?? '',
            'unit_cost'     => (float) $unitCost,
        ];
    }

    private function recipeCosting(Recipe $recipe): array
    {
        $recipe->loadMissing('yieldUom');

        return [
            'ingredient_id' => null,
            'recipe_id'     => $recipe->id,
            'uom_id'        => $recipe->yield_uom_id,
            'uom_abbr'      => $recipe->yieldUom?->abbreviation ?? '',
            'unit_cost'     => (float) $recipe->cost_per_yield_unit,
        ];
    }
}
