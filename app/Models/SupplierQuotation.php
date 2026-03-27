<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class SupplierQuotation extends Model
{
    protected $fillable = [
        'quotation_request_id', 'quotation_request_supplier_id', 'supplier_id',
        'quotation_number', 'status', 'valid_until',
        'subtotal', 'tax_rate_id', 'tax_amount', 'delivery_charges', 'total_amount',
        'notes', 'submitted_at',
    ];

    protected $casts = [
        'valid_until'      => 'date',
        'subtotal'         => 'decimal:4',
        'tax_amount'       => 'decimal:4',
        'delivery_charges' => 'decimal:4',
        'total_amount'     => 'decimal:4',
        'submitted_at'     => 'datetime',
    ];

    public function quotationRequest(): BelongsTo { return $this->belongsTo(QuotationRequest::class); }
    public function requestSupplier(): BelongsTo { return $this->belongsTo(QuotationRequestSupplier::class, 'quotation_request_supplier_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function taxRate(): BelongsTo { return $this->belongsTo(TaxRate::class); }
    public function lines(): HasMany { return $this->hasMany(SupplierQuotationLine::class); }

    public static function generateNumber(): string
    {
        $prefix = 'QTN-' . Carbon::now()->format('Ymd') . '-';
        $latest = static::where('quotation_number', 'like', "{$prefix}%")
            ->orderByDesc('quotation_number')
            ->value('quotation_number');
        $seq = $latest ? ((int) substr($latest, strrpos($latest, '-') + 1) + 1) : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
