<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class CreditNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'credit_note_number', 'type', 'direction', 'status',
        'supplier_id', 'outlet_id', 'procurement_invoice_id',
        'goods_received_note_id', 'purchase_order_id',
        'issued_date', 'due_date', 'subtotal', 'tax_rate_id', 'tax_amount', 'total_amount',
        'reason', 'notes', 'created_by',
    ];

    protected $casts = [
        'issued_date'  => 'date',
        'due_date'     => 'date',
        'subtotal'     => 'decimal:4',
        'tax_amount'   => 'decimal:4',
        'total_amount' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function procurementInvoice(): BelongsTo { return $this->belongsTo(ProcurementInvoice::class); }
    public function goodsReceivedNote(): BelongsTo { return $this->belongsTo(GoodsReceivedNote::class); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function taxRate(): BelongsTo { return $this->belongsTo(TaxRate::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lines(): HasMany { return $this->hasMany(CreditNoteLine::class); }

    public static function generateNumber(string $type): string
    {
        $prefix = ($type === 'debit_note' ? 'DN-' : 'CN-') . Carbon::now()->format('Ymd') . '-';
        $latest = static::withoutGlobalScopes()
            ->where('credit_note_number', 'like', "{$prefix}%")
            ->orderByDesc('credit_note_number')
            ->value('credit_note_number');
        $seq = $latest ? ((int) substr($latest, strrpos($latest, '-') + 1) + 1) : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
