<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestLine extends Model
{
    protected $fillable = [
        'purchase_request_id', 'ingredient_id', 'custom_name',
        'quantity', 'uom_id', 'preferred_supplier_id', 'source', 'kitchen_id', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function preferredSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(CentralKitchen::class, 'kitchen_id');
    }

    public function isKitchenItem(): bool
    {
        return $this->source === 'kitchen';
    }
}
