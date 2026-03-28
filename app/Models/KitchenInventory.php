<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenInventory extends Model
{
    protected $table = 'kitchen_inventory';

    protected $fillable = [
        'kitchen_id', 'ingredient_id', 'quantity_on_hand', 'uom_id', 'unit_cost',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:4',
        'unit_cost'        => 'decimal:4',
    ];

    public function kitchen(): BelongsTo { return $this->belongsTo(CentralKitchen::class, 'kitchen_id'); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function uom(): BelongsTo { return $this->belongsTo(UnitOfMeasure::class, 'uom_id'); }

    /**
     * Add stock after production.
     */
    public static function addStock(int $kitchenId, int $ingredientId, float $quantity, int $uomId, float $unitCost): void
    {
        $inv = static::firstOrCreate(
            ['kitchen_id' => $kitchenId, 'ingredient_id' => $ingredientId],
            ['quantity_on_hand' => 0, 'uom_id' => $uomId, 'unit_cost' => $unitCost]
        );
        $inv->increment('quantity_on_hand', $quantity);
        $inv->update(['unit_cost' => $unitCost, 'uom_id' => $uomId]);
    }

    /**
     * Deduct stock when fulfilling an outlet order.
     */
    public static function deductStock(int $kitchenId, int $ingredientId, float $quantity): bool
    {
        $inv = static::where('kitchen_id', $kitchenId)
            ->where('ingredient_id', $ingredientId)
            ->first();

        if (! $inv || floatval($inv->quantity_on_hand) < $quantity) return false;

        $inv->decrement('quantity_on_hand', $quantity);
        return true;
    }
}
