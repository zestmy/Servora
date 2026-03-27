<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'outlet_id', 'supplier_id',
        'po_number', 'status', 'order_date', 'expected_delivery_date',
        'total_amount', 'subtotal', 'tax_percent', 'tax_amount',
        'notes', 'receiver_name', 'department_id', 'created_by', 'approved_by',
        'purchase_request_id', 'cpu_id', 'source', 'delivery_charges', 'delivery_outlet_id',
        'is_multi_supplier', 'parent_po_id', 'tax_rate_id',
    ];

    protected $casts = [
        'order_date'             => 'date',
        'expected_delivery_date' => 'date',
        'total_amount'           => 'decimal:4',
        'subtotal'               => 'decimal:4',
        'tax_percent'            => 'decimal:2',
        'tax_amount'             => 'decimal:4',
        'delivery_charges'       => 'decimal:4',
        'is_multi_supplier'      => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function goodsReceivedNotes(): HasMany
    {
        return $this->hasMany(GoodsReceivedNote::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function cpu(): BelongsTo
    {
        return $this->belongsTo(CentralPurchasingUnit::class, 'cpu_id');
    }

    public function deliveryOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'delivery_outlet_id');
    }

    public function parentPo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_po_id');
    }

    public function childPos(): HasMany
    {
        return $this->hasMany(self::class, 'parent_po_id');
    }

    public function isFullyReceived(): bool
    {
        return $this->lines->every(fn ($l) => $l->isFullyReceived());
    }

    public function isPartiallyReceived(): bool
    {
        return $this->lines->some(fn ($l) => floatval($l->received_quantity) > 0)
            && ! $this->isFullyReceived();
    }

    public function receivedPercentage(): float
    {
        $totalQty = $this->lines->sum(fn ($l) => floatval($l->quantity));
        if ($totalQty <= 0) return 0;
        $receivedQty = $this->lines->sum(fn ($l) => floatval($l->received_quantity));
        return round(($receivedQty / $totalQty) * 100, 1);
    }
}
