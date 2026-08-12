<?php

namespace App\Models;

use App\Models\Concerns\PurgesStoredFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeStep extends Model
{
    use PurgesStoredFiles;

    protected $fillable = ['recipe_id', 'sort_order', 'title', 'instruction', 'image_path'];

    protected static function booted(): void
    {
        // A step's picture belongs to the step. See RecipeImage for why the
        // shared-path guard is there rather than a plain delete.
        static::deleted(fn (self $step) => $step->purgeOwnedFile('image_path'));
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path)
            : null;
    }
}
