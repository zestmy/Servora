<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReferralCode extends Model
{
    protected $fillable = [
        'referrer_type', 'referrer_id', 'code', 'url',
        'total_clicks', 'total_signups', 'total_conversions', 'is_active',
    ];

    protected $casts = [
        'total_clicks'      => 'integer',
        'total_signups'     => 'integer',
        'total_conversions' => 'integer',
        'is_active'         => 'boolean',
    ];

    public function referrer(): MorphTo
    {
        return $this->morphTo();
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function incrementClicks(): void
    {
        $this->increment('total_clicks');
    }

    public function incrementSignups(): void
    {
        $this->increment('total_signups');
    }

    public function incrementConversions(): void
    {
        $this->increment('total_conversions');
    }
}
