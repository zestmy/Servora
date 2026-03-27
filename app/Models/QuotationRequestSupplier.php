<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QuotationRequestSupplier extends Model
{
    protected $fillable = [
        'quotation_request_id', 'supplier_id', 'status',
        'sent_at', 'responded_at', 'notes',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function quotationRequest(): BelongsTo { return $this->belongsTo(QuotationRequest::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function quotation(): HasOne { return $this->hasOne(SupplierQuotation::class); }
}
