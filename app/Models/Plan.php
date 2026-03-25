<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description',
        'price_monthly', 'price_yearly', 'currency',
        'max_outlets', 'max_users', 'max_recipes', 'max_ingredients', 'max_lms_users',
        'feature_flags', 'is_active', 'sort_order', 'trial_days', 'api_rate_limit',
    ];

    protected $casts = [
        'price_monthly'   => 'decimal:2',
        'price_yearly'    => 'decimal:2',
        'max_outlets'     => 'integer',
        'max_users'       => 'integer',
        'max_recipes'     => 'integer',
        'max_ingredients' => 'integer',
        'max_lms_users'   => 'integer',
        'feature_flags'   => 'array',
        'is_active'       => 'boolean',
        'sort_order'      => 'integer',
        'trial_days'      => 'integer',
        'api_rate_limit'  => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasFeature(string $feature): bool
    {
        $flags = $this->feature_flags ?? [];

        return !empty($flags[$feature]);
    }

    public function getLimit(string $metric): ?int
    {
        return match ($metric) {
            'outlets'     => $this->max_outlets,
            'users'       => $this->max_users,
            'recipes'     => $this->max_recipes,
            'ingredients' => $this->max_ingredients,
            'lms_users'   => $this->max_lms_users,
            default       => null,
        };
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price_monthly');
    }

    public function yearlyDiscount(): float
    {
        $monthlyTotal = $this->price_monthly * 12;
        if ($monthlyTotal <= 0) {
            return 0;
        }

        return round((1 - $this->price_yearly / $monthlyTotal) * 100);
    }
}
