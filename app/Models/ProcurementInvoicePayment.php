<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementInvoicePayment extends Model
{
    protected $fillable = [
        'company_id', 'procurement_invoice_id',
        'payment_date', 'amount', 'method', 'reference', 'notes',
        'recorded_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'decimal:4',
    ];

    public const METHODS = [
        'bank_transfer' => 'Bank Transfer',
        'cash'          => 'Cash',
        'cheque'        => 'Cheque',
        'credit_card'   => 'Credit Card',
        'ewallet'       => 'E-Wallet',
        'other'         => 'Other',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(ProcurementInvoice::class, 'procurement_invoice_id'); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst(str_replace('_', ' ', $this->method));
    }
}
