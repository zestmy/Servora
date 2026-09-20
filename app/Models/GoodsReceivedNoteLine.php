<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceivedNoteLine extends Model
{
    protected $fillable = [
        'goods_received_note_id', 'ingredient_id', 'asset_id', 'expected_quantity',
        'received_quantity', 'uom_id', 'unit_cost', 'total_cost', 'condition',
        'tax_rate_id', 'tax_amount',
    ];

    protected $casts = [
        'expected_quantity'  => 'decimal:4',
        'received_quantity'  => 'decimal:4',
        'unit_cost'          => 'decimal:4',
        'total_cost'         => 'decimal:4',
        'tax_amount'         => 'decimal:4',
    ];

    public function goodsReceivedNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

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
