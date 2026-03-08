<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IngredientCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ingredient_categories';

    protected $fillable = ['company_id', 'parent_id', 'type', 'name', 'color', 'sort_order', 'is_active', 'is_revenue'];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_revenue' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    // ── Relations ─────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'parent_id')->withoutGlobalScopes();
    }

    public function children(): HasMany
    {
        return $this->hasMany(IngredientCategory::class, 'parent_id')
                    ->orderBy('sort_order')
                    ->orderBy('name');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class, 'ingredient_category_id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'ingredient_category_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeRoots(Builder $q): Builder
    {
        return $q->whereNull('parent_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    public function scopeRevenue(Builder $q): Builder
    {
        return $q->where('is_revenue', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * The cost type for costing reports.
     * Only main categories (parent_id = null) have a type; sub-categories always return null.
     */
    public function resolvedType(): ?string
    {
        return $this->parent_id ? null : $this->type;
    }

    /** Cost-grouping types — loaded from cost_types table. */
    public static function typeOptions(): array
    {
        return CostType::options();
    }

    /** Preset color palette available to users. */
    public static function colorOptions(): array
    {
        return [
            '#ef4444' => 'Red',
            '#f97316' => 'Orange',
            '#eab308' => 'Yellow',
            '#22c55e' => 'Green',
            '#14b8a6' => 'Teal',
            '#3b82f6' => 'Blue',
            '#6366f1' => 'Indigo',
            '#a855f7' => 'Purple',
            '#ec4899' => 'Pink',
            '#6b7280' => 'Gray',
        ];
    }
}
