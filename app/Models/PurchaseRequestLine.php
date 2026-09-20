<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestLine extends Model
{
    protected $fillable = [
        'purchase_request_id', 'ingredient_id', 'asset_id', 'custom_name',
        'quantity', 'uom_id', 'preferred_supplier_id', 'source', 'kitchen_id', 'notes',
        'tax_rate_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    /**
     * Where a line is expected to be satisfied from.
     *
     * 'supplier' and 'kitchen' were string literals scattered across the form,
     * the consolidator and the routing service. 'asset' joins them rather than
     * being inferred from asset_id alone, so consolidation can say out loud
     * why it left a line behind instead of a reader having to work out that a
     * line with no ingredient on it is skipped.
     */
    public const SOURCE_SUPPLIER = 'supplier';
    public const SOURCE_KITCHEN  = 'kitchen';
    public const SOURCE_ASSET    = 'asset';

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
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

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function isKitchenItem(): bool
    {
        return $this->source === 'kitchen';
    }

    /** A line asking for an asset rather than something to cook with. */
    public function isAssetItem(): bool
    {
        return $this->source === self::SOURCE_ASSET || $this->asset_id !== null;
    }

    /**
     * What this line is asking for, whatever kind of thing that is.
     *
     * One method because there are now three answers — an ingredient, an asset,
     * or a name somebody typed — and every screen, PDF and export that prints a
     * request line has to give the same one. The two that existed were already
     * spelt out inline in three places before assets arrived.
     */
    public function displayName(): string
    {
        return $this->ingredient?->name
            ?? $this->asset?->name
            ?? $this->custom_name
            ?? '—';
    }
}
