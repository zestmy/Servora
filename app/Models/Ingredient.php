<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'code', 'base_uom_id', 'recipe_uom_id',
        'purchase_price', 'yield_percent', 'current_cost', 'category',
        'ingredient_category_id', 'is_active', 'is_prep', 'prep_recipe_id',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'is_prep'        => 'boolean',
        'purchase_price' => 'decimal:4',
        'yield_percent'  => 'decimal:2',
        'current_cost'   => 'decimal:4',
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

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    public function recipeUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'recipe_uom_id');
    }

    /** The prep recipe that produces this ingredient (prep items only). */
    public function prepRecipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'prep_recipe_id');
    }

    public function uomConversions(): HasMany
    {
        return $this->hasMany(IngredientUomConversion::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(IngredientPriceHistory::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_ingredients')
            ->withPivot(['supplier_sku', 'last_cost', 'uom_id', 'is_preferred'])
            ->withTimestamps();
    }

    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }

    /**
     * Cost per recipe UOM, derived from current_cost + UOM conversions.
     * Returns null when no matching conversion exists.
     */
    public function recipeCost(): ?float
    {
        if ($this->base_uom_id === $this->recipe_uom_id) {
            return (float) $this->current_cost;
        }

        foreach ($this->uomConversions as $c) {
            // 1 base = factor recipe  →  cost/recipe = cost/base ÷ factor
            if ($c->from_uom_id === $this->base_uom_id && $c->to_uom_id === $this->recipe_uom_id) {
                return (float) $this->current_cost / (float) $c->factor;
            }
            // 1 recipe = factor base  →  cost/recipe = cost/base × factor
            if ($c->from_uom_id === $this->recipe_uom_id && $c->to_uom_id === $this->base_uom_id) {
                return (float) $this->current_cost * (float) $c->factor;
            }
        }

        return null;
    }
}
