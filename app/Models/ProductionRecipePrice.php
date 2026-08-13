<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One production recipe's price under one price class.
 *
 * The classes themselves are the outlet recipes' — RecipePriceClass — because
 * "Dine In" and "Delivery" mean the same thing whoever cooked the item, and a
 * company should define them once.
 */
class ProductionRecipePrice extends Model
{
    protected $fillable = ['production_recipe_id', 'recipe_price_class_id', 'selling_price'];

    protected $casts = ['selling_price' => 'decimal:4'];

    public function productionRecipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class);
    }

    public function priceClass(): BelongsTo
    {
        return $this->belongsTo(RecipePriceClass::class, 'recipe_price_class_id');
    }
}
