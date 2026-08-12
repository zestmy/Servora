<?php

namespace App\Models;

use App\Models\Concerns\PurgesStoredFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One preparation/SOP step of a central-kitchen production recipe. */
class ProductionRecipeStep extends Model
{
    use PurgesStoredFiles;

    protected $fillable = [
        'production_recipe_id', 'sort_order', 'title', 'instruction', 'image_path',
    ];

    protected static function booted(): void
    {
        static::deleted(fn (self $step) => $step->purgeOwnedFile('image_path'));
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class, 'production_recipe_id');
    }
}
