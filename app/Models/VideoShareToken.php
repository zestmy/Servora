<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VideoShareToken extends Model
{
    protected $fillable = ['token', 'recipe_id', 'company_id'];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get or create a permanent share token for a recipe.
     */
    public static function forRecipe(int $recipeId, int $companyId): self
    {
        return static::firstOrCreate(
            ['recipe_id' => $recipeId, 'company_id' => $companyId],
            ['token' => Str::random(32)]
        );
    }
}
