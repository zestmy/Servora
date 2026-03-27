<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationRequestLine extends Model
{
    protected $fillable = ['quotation_request_id', 'ingredient_id', 'quantity', 'uom_id', 'notes'];

    protected $casts = ['quantity' => 'decimal:4'];

    public function quotationRequest(): BelongsTo { return $this->belongsTo(QuotationRequest::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function uom(): BelongsTo { return $this->belongsTo(UnitOfMeasure::class, 'uom_id'); }
}
