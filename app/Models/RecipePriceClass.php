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

        /*
         * ONE DEFAULT PER LIST, enforced here rather than on the screen.
         *
         * Production had two outlet classes flagged default — "Price (3/7/26)"
         * with 317 prices against it and "KLCC" with 40 — and the callers did
         * not agree which one won: SmartImport takes ->where('is_default')
         * ->first(), which is primary-key order, while the recipe form reads
         * ordered()->firstWhere(), which is sort_order then name. Same data,
         * two answers, on two screens.
         *
         * The settings screen already cleared the others, so the state had to
         * have arrived some other way — a seeder, an import, a console fix.
         * A rule that only one entry point applies is not a rule, so it lives
         * on the model, where a seeder and a tinker session obey it too.
         *
         * Global scopes are dropped and company_id matched by hand because
         * this must also hold when nobody is signed in. update() on a query
         * builder fires no events, so this cannot recurse.
         */
        static::saved(function (self $class): void {
            if (! $class->is_default) {
                return;
            }

            static::withoutGlobalScopes()
                ->where('company_id', $class->company_id)
                ->where('scope', $class->scope)
                ->where('id', '!=', $class->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
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
