<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTakeLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_take_id', 'ingredient_id', 'system_quantity', 'actual_quantity',
        'pack_quantity', 'loose_quantity',
        'variance_quantity', 'uom_id', 'unit_cost', 'variance_cost',
    ];

    protected $casts = [
        'system_quantity' => 'decimal:4',
        'actual_quantity' => 'decimal:4',
        'pack_quantity' => 'decimal:4',
        'loose_quantity' => 'decimal:4',
        'variance_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'variance_cost' => 'decimal:4',
    ];

    /**
     * How many recipe units one purchase unit holds for a line stored in
     * $storedUom, or null when the line has no separate pack column.
     *
     * Null when the line is already stored in the purchase unit (no recipe
     * UOM), or when nothing links the two units: counting "2 batches" as
     * 2 pieces would record stock off by the pack size, so without a real
     * conversion there is only the one column. Shared by the form and the
     * printed count sheet so the sheet asks for what the form accepts.
     */
    public static function packFactor(?Ingredient $ingredient, ?UnitOfMeasure $storedUom): ?float
    {
        if (! $ingredient || ! $ingredient->baseUom || ! $storedUom) {
            return null;
        }

        if ((int) $storedUom->id === (int) $ingredient->base_uom_id
            || (int) $storedUom->id !== (int) $ingredient->recipe_uom_id) {
            return null;
        }

        $factor = $ingredient->recipeUnitsPerBaseUnit();

        return $factor && $factor > 0 ? $factor : null;
    }

    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
