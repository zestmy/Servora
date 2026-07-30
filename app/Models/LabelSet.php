<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named collection a chef prints in one go — "Chiller 1", "Grill Station".
 *
 * Outlet-owned: there are no company-wide sets. Named LabelSet rather than
 * LabelGroup because OutletGroup already exists.
 */
class LabelSet extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'name', 'description',
        'sort_order', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Lines in print order. The order is physical — labels peel off the roll
     * in sequence and get applied walking down the shelf — so it is always
     * applied, never left to insertion order.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(LabelSetLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForOutlet(Builder $query, int $outletId): Builder
    {
        return $query->where('outlet_id', $outletId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
