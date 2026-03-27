<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id', 'ingredient_id', 'quantity', 'original_quantity',
        'uom_id', 'unit_cost', 'total_cost', 'received_quantity',
        'adjusted_by', 'adjustment_reason',
    ];

    protected $casts = [
        'quantity'          => 'decimal:4',
        'original_quantity' => 'decimal:4',
        'unit_cost'         => 'decimal:4',
        'total_cost'        => 'decimal:4',
        'received_quantity' => 'decimal:4',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function adjustedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function adjustmentLogs(): MorphMany
    {
        return $this->morphMany(OrderAdjustmentLog::class, 'adjustable');
    }

    public function isAdjusted(): bool
    {
        return $this->original_quantity !== null;
    }

    public function remainingQuantity(): float
    {
        return max(0, floatval($this->quantity) - floatval($this->received_quantity));
    }

    public function isFullyReceived(): bool
    {
        return floatval($this->received_quantity) >= floatval($this->quantity);
    }
}
