<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementInvoiceLine extends Model
{
    protected $fillable = [
        'procurement_invoice_id', 'ingredient_id', 'asset_id', 'description',
        'quantity', 'uom_id', 'unit_price', 'total_price',
    ];

    protected $casts = [
        'quantity'    => 'decimal:4',
        'unit_price'  => 'decimal:4',
        'total_price' => 'decimal:4',
    ];

    public function procurementInvoice(): BelongsTo { return $this->belongsTo(ProcurementInvoice::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function uom(): BelongsTo { return $this->belongsTo(UnitOfMeasure::class, 'uom_id'); }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** An asset line, not an ingredient line. The id is the fact. */
    public function isAssetItem(): bool
    {
        return $this->asset_id !== null;
    }

    /** What to show, whichever kind of line it is. */
    public function displayName(): string
    {
        return $this->asset?->name ?? $this->ingredient?->name ?? '—';
    }
}
