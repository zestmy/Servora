<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletTransferLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_transfer_id', 'ingredient_id', 'recipe_id', 'asset_id', 'custom_name', 'quantity', 'uom_id', 'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
    ];

    public function outletTransfer(): BelongsTo
    {
        return $this->belongsTo(OutletTransfer::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** An asset line: it moves the asset register, never stock on hand. */
    public function isAssetItem(): bool
    {
        return $this->asset_id !== null;
    }

    /** What the line is called, whichever of the four kinds it is. */
    public function getItemNameAttribute(): string
    {
        return $this->ingredient?->name ?? $this->recipe?->name ?? $this->asset?->name ?? $this->custom_name ?? '-';
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
