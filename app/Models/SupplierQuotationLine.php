<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierQuotationLine extends Model
{
    protected $fillable = [
        'supplier_quotation_id', 'quotation_request_line_id', 'ingredient_id',
        'quantity', 'uom_id', 'unit_price', 'total_price',
        'price_type', 'discount_percent', 'notes',
    ];

    protected $casts = [
        'quantity'         => 'decimal:4',
        'unit_price'       => 'decimal:4',
        'total_price'      => 'decimal:4',
        'discount_percent' => 'decimal:2',
    ];

    public function supplierQuotation(): BelongsTo { return $this->belongsTo(SupplierQuotation::class); }
    public function requestLine(): BelongsTo { return $this->belongsTo(QuotationRequestLine::class, 'quotation_request_line_id'); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function uom(): BelongsTo { return $this->belongsTo(UnitOfMeasure::class, 'uom_id'); }
}
