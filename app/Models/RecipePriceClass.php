<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipePriceClass extends Model
{
    protected $fillable = ['company_id', 'scope', 'name', 'sort_order', 'is_default'];

    /*
     * TWO LISTS, ONE TABLE, exactly as recipe_categories does it.
     *
     * The kitchen was given the outlet's classes when production recipes
     * gained a Selling Price section, on the reasoning that "Delivery" means
     * the same thing whoever cooked the item. It does not: a kitchen sells to
     * its own branches and to wholesale, an outlet sells to diners, and one
     * list forces both sets of names onto both screens.
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
        return $query->where('recipe_price_classes.scope', $scope);
    }

    public function scopeOutletScope($query)
    {
        return $query->inScope(self::SCOPE_OUTLET);
    }

    public function scopeKitchenScope($query)
    {
        return $query->inScope(self::SCOPE_KITCHEN);
    }

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(RecipePrice::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
