<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'subscription_id', 'chip_payment_id', 'chip_purchase_id',
        'amount', 'currency', 'status', 'payment_method', 'paid_at', 'metadata',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'paid_at'  => 'datetime',
        'metadata' => 'array',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_REFUNDED  = 'refunded';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * withTrashed: a payment's subscription is history and must still resolve
     * after that subscription follows its company into the bin — the ChipIn
     * webhook and InvoiceService both dereference it without a null check.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class)->withTrashed();
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
