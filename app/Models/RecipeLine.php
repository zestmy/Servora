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
}
