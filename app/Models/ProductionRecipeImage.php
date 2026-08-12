<?php

namespace App\Models;

use App\Models\Concerns\PurgesStoredFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A product/presentation photo of a central-kitchen production recipe. */
class ProductionRecipeImage extends Model
{
    use PurgesStoredFiles;

    protected $fillable = [
        'production_recipe_id', 'file_name', 'file_path', 'mime_type', 'file_size', 'sort_order',
    ];

    protected static function booted(): void
    {
        static::deleted(fn (self $image) => $image->purgeOwnedFile('file_path'));
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class, 'production_recipe_id');
    }
}
