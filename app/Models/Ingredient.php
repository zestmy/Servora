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
        'purchase_price', 'pack_size', 'yield_percent', 'current_cost', 'category',
        'ingredient_category_id', 'tax_rate_id', 'is_active', 'is_prep', 'prep_recipe_id', 'remark',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'is_prep'        => 'boolean',
        'purchase_price' => 'decimal:4',
        'pack_size'      => 'decimal:4',
        'yield_percent'  => 'decimal:2',
        'current_cost'   => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::saving(function ($model) {
            if ($model->name) {
                $model->name = strtoupper($model->name);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function ingredientCategory(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'ingredient_category_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * Get the effective tax rate for this ingredient.
     * Returns ingredient-specific rate, or company default, or null.
     */
    public function effectiveTaxRate(?Company $company = null): ?TaxRate
    {
        if ($this->tax_rate_id) {
            return $this->taxRate;
        }

        return TaxRate::defaultForCompany($company);
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

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'outlet_ingredient')->withTimestamps();
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_ingredients')
            ->withPivot(['supplier_sku', 'last_cost', 'uom_id', 'pack_size', 'is_preferred'])
            ->withTimestamps();
    }

    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }

    /**
     * Cost per recipe UOM, derived from current_cost + UOM conversions.
     * Falls back to standard base_unit_factor for same-type UOMs (kg↔g, L↔mL).
     * Returns null when no matching conversion exists.
     */
    public function recipeCost(): ?float
    {
        $baseId   = (int) $this->base_uom_id;
        $recipeId = (int) $this->recipe_uom_id;

        if ($baseId === $recipeId) {
            return (float) $this->current_cost;
        }

        // Ingredient-specific conversions first
        foreach ($this->uomConversions as $c) {
            $from = (int) $c->from_uom_id;
            $to   = (int) $c->to_uom_id;
            if ($from === $baseId && $to === $recipeId) {
                return (float) $this->current_cost / (float) $c->factor;
            }
            if ($from === $recipeId && $to === $baseId) {
                return (float) $this->current_cost * (float) $c->factor;
            }
        }

        // Standard base_unit_factor fallback (same-type UOMs)
        $baseUom = $this->baseUom;
        $recipeUom = $this->recipeUom;
        if ($baseUom && $recipeUom
            && $baseUom->base_unit_factor && $recipeUom->base_unit_factor
            && $baseUom->type === $recipeUom->type) {
            $factor = (float) $recipeUom->base_unit_factor / (float) $baseUom->base_unit_factor;
            return (float) $this->current_cost * $factor;
        }

        return null;
    }
}
