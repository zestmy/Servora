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
        'purchase_order_id', 'ingredient_id', 'asset_id', 'supplier_sku', 'supplier_product_name',
        'quantity', 'original_quantity',
        'uom_id', 'unit_cost', 'total_cost', 'tax_rate_id', 'tax_amount',
        'received_quantity', 'adjusted_by', 'adjustment_reason',
    ];

    protected $casts = [
        'quantity'          => 'decimal:4',
        'original_quantity' => 'decimal:4',
        'unit_cost'         => 'decimal:4',
        'total_cost'        => 'decimal:4',
        'tax_amount'        => 'decimal:4',
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

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * An asset line, not an ingredient line.
     *
     * Read off asset_id rather than a `source` column: a purchase order line
     * has never had one, and the id IS the fact — the two are filled in
     * exclusively, and every guard downstream keys off exactly this.
     */
    public function isAssetItem(): bool
    {
        return $this->asset_id !== null;
    }

    /** What to print on the order, whichever kind of line it is. */
    public function displayName(): string
    {
        return $this->asset?->name
            ?? $this->ingredient?->name
            ?? $this->supplier_product_name
            ?? '—';
    }

    /**
     * Ordinary ingredient lines.
     *
     * Named so the places that must not see an asset say so out loud. An
     * asset cannot go down the receiving chain, which ends in a stock receipt
     * (`purchase_record_lines`), so a bare ->lines that happens to work today
     * is a trap for whoever writes the next consumer.
     */
    public function scopeIngredientLines($query)
    {
        return $query->whereNotNull('ingredient_id');
    }

    public function scopeAssetLines($query)
    {
        return $query->whereNotNull('asset_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
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
