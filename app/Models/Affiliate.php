<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Affiliate extends Authenticatable
{
    protected $fillable = [
        'name', 'email', 'password', 'phone',
        'bank_name', 'bank_account_name', 'bank_account_number', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function referralCode()
    {
        return ReferralCode::where('referrer_type', 'affiliate')
            ->where('referrer_id', $this->id)
            ->first();
    }
}
