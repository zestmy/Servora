<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    protected $fillable = [
        'referral_code_id', 'referred_company_id', 'status', 'converted_at',
    ];

    protected $casts = [
        'converted_at' => 'datetime',
    ];

    public const STATUS_SIGNED_UP = 'signed_up';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_PAID      = 'paid';

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }

    public function referredCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'referred_company_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
