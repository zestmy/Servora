<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'description', 'plan_id', 'grant_type', 'grant_value',
        'max_redemptions', 'redeemed_count', 'expires_at', 'is_active', 'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active'  => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_redemptions !== null && $this->redeemed_count >= $this->max_redemptions;
    }

    public function isRedeemable(): bool
    {
        return $this->is_active && ! $this->isExpired() && ! $this->isExhausted();
    }

    public function grantLabel(): string
    {
        return match ($this->grant_type) {
            'lifetime' => 'Lifetime',
            'months'   => $this->grant_value . ' month' . ($this->grant_value > 1 ? 's' : ''),
            'days'     => $this->grant_value . ' day' . ($this->grant_value > 1 ? 's' : ''),
            default    => '—',
        };
    }
}
