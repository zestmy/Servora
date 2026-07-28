<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecipeCategory extends Model
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'scope', 'parent_id', 'name', 'color', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /**
     * Which workspace a category belongs to. Outlet menu categories and
     * Central Kitchen production categories are separate vocabularies — a
     * query that doesn't pick one will mix both lists together.
     */
    public const SCOPE_OUTLET  = 'outlet';
    public const SCOPE_KITCHEN = 'kitchen';

    public const SCOPES = [
        self::SCOPE_OUTLET  => 'Outlet menu',
        self::SCOPE_KITCHEN => 'Central Kitchen',
    ];

    /** The scope matching the caller's active workspace. */
    public static function currentScope(): string
    {
        return \Illuminate\Support\Facades\Auth::user()?->inKitchenMode()
            ? self::SCOPE_KITCHEN
            : self::SCOPE_OUTLET;
    }

    public function scopeInScope($query, string $scope)
    {
        return $query->where('recipe_categories.scope', $scope);
    }

    /** Outlet menu categories — the default for every non-kitchen screen. */
    public function scopeOutletScope($query)
    {
        return $query->inScope(self::SCOPE_OUTLET);
    }

    /** Central Kitchen production categories. */
    public function scopeKitchenScope($query)
    {
        return $query->inScope(self::SCOPE_KITCHEN);
    }

    /** Whichever list belongs to the active workspace. */
    public function scopeForWorkspace($query)
    {
        return $query->inScope(self::currentScope());
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

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
