<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipePrice extends Model
{
    protected $fillable = ['recipe_id', 'recipe_price_class_id', 'selling_price'];

    protected $casts = [
        'selling_price' => 'decimal:4',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function priceClass(): BelongsTo
    {
        return $this->belongsTo(RecipePriceClass::class, 'recipe_price_class_id');
    }
}
