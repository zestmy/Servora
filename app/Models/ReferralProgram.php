<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralProgram extends Model
{
    protected $fillable = [
        'plan_id', 'commission_type', 'commission_value', 'is_recurring', 'max_payouts', 'is_active',
    ];

    protected $casts = [
        'commission_value' => 'decimal:2',
        'is_recurring'     => 'boolean',
        'is_active'        => 'boolean',
        'max_payouts'      => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function calculateCommission(float $paymentAmount): float
    {
        return match ($this->commission_type) {
            'percentage' => round($paymentAmount * $this->commission_value / 100, 2),
            'flat'       => (float) $this->commission_value,
            default      => 0,
        };
    }

    public function label(): string
    {
        return match ($this->commission_type) {
            'percentage' => $this->commission_value . '%',
            'flat'       => 'RM ' . number_format($this->commission_value, 2),
            default      => (string) $this->commission_value,
        };
    }
}
