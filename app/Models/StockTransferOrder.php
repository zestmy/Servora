<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransferOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'cpu_id', 'to_outlet_id', 'purchase_order_id',
        'sto_number', 'status', 'transfer_date', 'is_chargeable',
        'subtotal', 'tax_rate_id', 'tax_amount', 'delivery_charges', 'total_amount',
        'notes', 'created_by', 'received_by',
    ];

    protected $casts = [
        'transfer_date'    => 'date',
        'is_chargeable'    => 'boolean',
        'subtotal'         => 'decimal:4',
        'tax_amount'       => 'decimal:4',
        'delivery_charges' => 'decimal:4',
        'total_amount'     => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function cpu(): BelongsTo { return $this->belongsTo(CentralPurchasingUnit::class, 'cpu_id'); }
    public function toOutlet(): BelongsTo { return $this->belongsTo(Outlet::class, 'to_outlet_id'); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function taxRate(): BelongsTo { return $this->belongsTo(TaxRate::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function receivedBy(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferOrderLine::class);
    }

    public function procurementInvoice()
    {
        return $this->hasOne(ProcurementInvoice::class);
    }
}
