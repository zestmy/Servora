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
        'company_id', 'name', 'code', 'description', 'video_url', 'yield_quantity', 'yield_uom_id',
        'batch_multipliers', 'shelf_life_value', 'shelf_life_unit', 'storage_instruction',
        'selling_price', 'cost_per_yield_unit', 'extra_costs', 'category',
        'ingredient_category_id', 'department_id', 'is_active', 'is_prep',
        'exclude_from_lms', 'menu_sort_order',
    ];

    /** Shelf-life units selectable on prep items (value => label). */
    public const SHELF_LIFE_UNITS = [
        'minutes' => 'Minutes',
        'hours'   => 'Hours',
        'days'    => 'Days',
        'weeks'   => 'Weeks',
        'months'  => 'Months',
    ];

    /** Storing instructions selectable on prep items (value => label). */
    public const STORAGE_OPTIONS = [
        'chill'   => 'Chill',
        'frozen'  => 'Frozen',
        'ambient' => 'Ambient',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'is_prep'             => 'boolean',
        'exclude_from_lms'    => 'boolean',
        'menu_sort_order'     => 'integer',
        'yield_quantity'      => 'decimal:4',
        'selling_price'       => 'decimal:4',
        'cost_per_yield_unit' => 'decimal:4',
        'extra_costs'         => 'array',
        'batch_multipliers'   => 'array',
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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

    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)->orderBy('sort_order');
    }

    /** Outlets this recipe is tagged to (empty = available at all outlets). */
    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class)->withTimestamps();
    }

    /** Multiple selling prices per price class. */
    public function prices(): HasMany
    {
        return $this->hasMany(RecipePrice::class);
    }

    /** The synced ingredient record for this prep item. */
    public function ingredient(): HasOne
    {
        return $this->hasOne(Ingredient::class, 'prep_recipe_id');
    }

    /**
     * Extra batch sizes for prep items, as recipe multiples (1 Recipe = the
     * base yield). Cleaned: floats > 0, the base 1.0 removed, unique, sorted.
     */
    public function batchMultipliers(): array
    {
        return collect($this->batch_multipliers ?? [])
            ->map(fn ($m) => round((float) $m, 4))
            ->filter(fn ($m) => $m > 0 && abs($m - 1.0) > 0.0001)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** Format a batch multiplier for display: 0.5, 1.5, 2 (no trailing zeros). */
    public static function fmtMultiplier(float $m): string
    {
        return rtrim(rtrim(number_format($m, 4, '.', ''), '0'), '.') ?: '0';
    }

    /** Human-readable shelf life, e.g. "3 Days" / "1 Week" — null when not set. */
    public function shelfLifeLabel(): ?string
    {
        if (! $this->shelf_life_value || ! $this->shelf_life_unit) {
            return null;
        }

        $value = rtrim(rtrim(number_format((float) $this->shelf_life_value, 2, '.', ''), '0'), '.');
        $unit  = self::SHELF_LIFE_UNITS[$this->shelf_life_unit] ?? ucfirst($this->shelf_life_unit);

        if (abs((float) $this->shelf_life_value - 1.0) < 0.0001) {
            $unit = rtrim($unit, 's');
        }

        return "{$value} {$unit}";
    }

    /** Human-readable storing instruction, e.g. "Chill" — null when not set. */
    public function storageLabel(): ?string
    {
        return $this->storage_instruction
            ? (self::STORAGE_OPTIONS[$this->storage_instruction] ?? ucfirst($this->storage_instruction))
            : null;
    }

    /**
     * Limit to recipes visible for the given outlet ids: untagged recipes
     * (available everywhere) plus recipes tagged to any of the outlets.
     * An empty id list means unrestricted — no filter applied.
     */
    public function scopeVisibleToOutlets($query, array $outletIds)
    {
        if (empty($outletIds)) {
            return $query;
        }

        return $query->where(function ($q) use ($outletIds) {
            $q->whereDoesntHave('outlets')
              ->orWhereHas('outlets', fn ($o) => $o->whereIn('outlets.id', $outletIds));
        });
    }

    public function getTotalCostAttribute(): float
    {
        return $this->lines->sum(fn ($line) => $line->cost_per_recipe_uom * $line->quantity);
    }

    /**
     * Get the effective selling price — from default price class, or fallback to recipe.selling_price.
     */
    public function getEffectiveSellingPriceAttribute(): float
    {
        // Try default price class first
        if ($this->relationLoaded('prices') && $this->prices->isNotEmpty()) {
            $defaultPrice = $this->prices->first(fn ($p) => $p->priceClass?->is_default);
            if ($defaultPrice && floatval($defaultPrice->selling_price) > 0) {
                return floatval($defaultPrice->selling_price);
            }
            // Fallback to first price class with a value
            $anyPrice = $this->prices->first(fn ($p) => floatval($p->selling_price) > 0);
            if ($anyPrice) {
                return floatval($anyPrice->selling_price);
            }
        }

        return floatval($this->selling_price);
    }

    /** Cost per single yield unit (e.g. cost per portion of rice). */
    public function getCostPerYieldUnitAttribute(): float
    {
        $yield = max(floatval($this->yield_quantity), 0.0001);
        return $this->total_cost / $yield;
    }
}
