<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class ProcurementInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'outlet_id', 'supplier_id',
        'stock_transfer_order_id', 'purchase_order_id', 'goods_received_note_id',
        'invoice_number', 'type', 'status', 'issued_date', 'due_date',
        'subtotal', 'tax_rate_id', 'tax_amount', 'delivery_charges', 'total_amount',
        'currency', 'notes', 'created_by',
    ];

    protected $casts = [
        'issued_date'      => 'date',
        'due_date'         => 'date',
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
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function stockTransferOrder(): BelongsTo { return $this->belongsTo(StockTransferOrder::class); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function goodsReceivedNote(): BelongsTo { return $this->belongsTo(GoodsReceivedNote::class); }
    public function taxRate(): BelongsTo { return $this->belongsTo(TaxRate::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function lines(): HasMany
    {
        return $this->hasMany(ProcurementInvoiceLine::class);
    }

    public static function generateNumber(): string
    {
        $prefix = 'PINV-' . Carbon::now()->format('Ymd') . '-';
        $latest = static::withoutGlobalScopes()
            ->where('invoice_number', 'like', "{$prefix}%")
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $seq = 1;
        if ($latest) {
            $seq = (int) substr($latest, strrpos($latest, '-') + 1) + 1;
        }
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
