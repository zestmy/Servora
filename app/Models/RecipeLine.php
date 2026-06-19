<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\UomService;

class RecipeLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id', 'ingredient_id', 'quantity', 'uom_id', 'waste_percentage', 'sort_order', 'is_packaging',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'waste_percentage' => 'decimal:2',
        'is_packaging' => 'boolean',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Returns the cost of this ingredient per the recipe UOM, accounting for waste.
     */
    public function getCostPerRecipeUomAttribute(): float
    {
        if (! $this->ingredient || ! $this->uom) {
            return 0.0;
        }

        $service = app(UomService::class);
        $baseCostPerUom = $service->convertCost($this->ingredient, $this->uom);

        $wasteFactor = 1 + ((float) $this->waste_percentage / 100);

        return $baseCostPerUom * $wasteFactor;
    }

    public function getLineTotalCostAttribute(): float
    {
        return $this->cost_per_recipe_uom * (float) $this->quantity;
    }

    /**
     * How this line's quantity should be presented in LMS / SOP training.
     *
     * When the ingredient defines a secondary recipe UOM, training material shows
     * the secondary unit as the main figure (kitchen-friendly, e.g. "2 bsp") with
     * the primary recipe UOM in brackets for reference (e.g. "54 g"). Falls back to
     * the line's own UOM when no secondary unit is configured or cannot be resolved.
     *
     * @return array{main_qty:string, main_uom:string, ref_qty:?string, ref_uom:?string}
     */
    public function sopUomDisplay(): array
    {
        $qty = (float) $this->quantity;

        $fallback = [
            'main_qty' => $this->fmtQty($qty),
            'main_uom' => $this->uom?->abbreviation ?? '',
            'ref_qty'  => null,
            'ref_uom'  => null,
        ];

        $ingredient = $this->ingredient;
        if (! $ingredient || ! $ingredient->secondary_recipe_uom_id || ! $ingredient->recipe_uom_id) {
            return $fallback;
        }

        $primaryUom   = $ingredient->recipeUom;
        $secondaryUom = $ingredient->secondaryRecipeUom;
        if (! $primaryUom || ! $secondaryUom) {
            return $fallback;
        }

        $recipeId    = (int) $ingredient->recipe_uom_id;
        $secondaryId = (int) $ingredient->secondary_recipe_uom_id;

        // Conversion is stored as: from = secondary, to = recipe, factor = F (1 secondary = F primary).
        $conversion = $ingredient->relationLoaded('uomConversions')
            ? $ingredient->uomConversions->first(
                fn ($c) => (int) $c->from_uom_id === $secondaryId && (int) $c->to_uom_id === $recipeId
            )
            : $ingredient->uomConversions()
                ->where('from_uom_id', $secondaryId)
                ->where('to_uom_id', $recipeId)
                ->first();

        $factor = (float) ($conversion->factor ?? 0);
        if ($factor <= 0) {
            return $fallback;
        }

        // Express the stored quantity in the primary recipe UOM, then divide into the secondary.
        $lineUomId = (int) $this->uom_id;
        if ($lineUomId === $recipeId) {
            $qtyPrimary = $qty;
        } elseif ($lineUomId === $secondaryId) {
            $qtyPrimary = $qty * $factor;
        } elseif ($this->uom) {
            $qtyPrimary = app(UomService::class)->convertQuantity($qty, $this->uom, $primaryUom, $ingredient->id);
        } else {
            return $fallback;
        }

        return [
            'main_qty' => $this->fmtQty($qtyPrimary / $factor),
            'main_uom' => $secondaryUom->abbreviation,
            'ref_qty'  => $this->fmtQty($qtyPrimary),
            'ref_uom'  => $primaryUom->abbreviation,
        ];
    }

    private function fmtQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 4), '0'), '.') ?: '0';
    }
}
