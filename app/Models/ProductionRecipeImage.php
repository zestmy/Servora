<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A product/presentation photo of a central-kitchen production recipe. */
class ProductionRecipeImage extends Model
{
    protected $fillable = [
        'production_recipe_id', 'file_name', 'file_path', 'mime_type', 'file_size', 'sort_order',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class, 'production_recipe_id');
    }
}
