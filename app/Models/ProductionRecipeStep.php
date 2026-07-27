<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One preparation/SOP step of a central-kitchen production recipe. */
class ProductionRecipeStep extends Model
{
    protected $fillable = [
        'production_recipe_id', 'sort_order', 'title', 'instruction', 'image_path',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class, 'production_recipe_id');
    }
}
