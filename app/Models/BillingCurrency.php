<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A currency visitors can be priced in besides MYR (the base, which never
 * has a row). See Billing\CurrencyResolver for who gets which, and
 * Billing\PriceBook for what they are charged.
 */
class BillingCurrency extends Model
{
    public const MODE_CONVERTED = 'converted';
    public const MODE_FIXED     = 'fixed';

    protected $fillable = [
        'code', 'name', 'symbol', 'countries', 'is_rest_of_world', 'pricing_mode',
        'prices', 'rounding', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'countries'        => 'array',
        'prices'           => 'array',
        'is_rest_of_world' => 'boolean',
        'is_active'        => 'boolean',
        'rounding'         => 'float',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function covers(string $country): bool
    {
        return in_array(strtoupper($country), (array) $this->countries, true);
    }
}
