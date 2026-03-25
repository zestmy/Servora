<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'plan_id', 'status', 'billing_cycle',
        'trial_ends_at', 'current_period_start', 'current_period_end', 'cancelled_at',
    ];

    protected $casts = [
        'trial_ends_at'        => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end'   => 'datetime',
        'cancelled_at'         => 'datetime',
    ];

    public const STATUS_TRIALING  = 'trialing';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAST_DUE  = 'past_due';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED   = 'expired';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING]);
    }

    public function isTrial(): bool
    {
        return $this->status === self::STATUS_TRIALING;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function daysRemaining(): int
    {
        if ($this->isTrial() && $this->trial_ends_at) {
            return max(0, (int) now()->diffInDays($this->trial_ends_at, false));
        }

        if ($this->current_period_end) {
            return max(0, (int) now()->diffInDays($this->current_period_end, false));
        }

        return 0;
    }

    public function currentPrice(): float
    {
        if (!$this->plan) {
            return 0;
        }

        return $this->billing_cycle === 'yearly'
            ? (float) $this->plan->price_yearly
            : (float) $this->plan->price_monthly;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_TRIALING  => 'Trial',
            self::STATUS_ACTIVE    => 'Active',
            self::STATUS_PAST_DUE  => 'Past Due',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_EXPIRED   => 'Expired',
            default                => ucfirst($this->status),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_TRIALING  => 'blue',
            self::STATUS_ACTIVE    => 'green',
            self::STATUS_PAST_DUE  => 'amber',
            self::STATUS_CANCELLED => 'gray',
            self::STATUS_EXPIRED   => 'red',
            default                => 'gray',
        };
    }
}
