<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A module a company bought on top of its suite (config/modules.php
 * `catalogue`). One row per module per subscription; quantity is employees
 * for HR, kitchens for Central Kitchen, 1 for a flat add-on.
 */
class SubscriptionAddon extends Model
{
    protected $fillable = ['subscription_id', 'module', 'quantity', 'unit_price', 'ends_at'];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'decimal:2',
        'ends_at'    => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** Not ended: no end date, or one still ahead. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
