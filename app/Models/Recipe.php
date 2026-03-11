<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'code', 'description', 'yield_quantity', 'yield_uom_id',
        'selling_price', 'cost_per_yield_unit', 'category', 'ingredient_category_id',
        'is_active', 'is_prep',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'is_prep'             => 'boolean',
        'yield_quantity'      => 'decimal:4',
        'selling_price'       => 'decimal:4',
        'cost_per_yield_unit' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function ingredientCategory(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'ingredient_category_id');
    }

    public function yieldUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'yield_uom_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RecipeLine::class)->orderBy('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(RecipeImage::class)->orderBy('sort_order');
    }

    /** Outlets this recipe is tagged to (empty = available at all outlets). */
    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class)->withTimestamps();
    }

    /** The synced ingredient record for this prep item. */
    public function ingredient(): HasOne
    {
        return $this->hasOne(Ingredient::class, 'prep_recipe_id');
    }

    public function getTotalCostAttribute(): float
    {
        return $this->lines->sum(fn ($line) => $line->cost_per_recipe_uom * $line->quantity);
    }

    /** Cost per single yield unit (e.g. cost per portion of rice). */
    public function getCostPerYieldUnitAttribute(): float
    {
        $yield = max(floatval($this->yield_quantity), 0.0001);
        return $this->total_cost / $yield;
    }
}
