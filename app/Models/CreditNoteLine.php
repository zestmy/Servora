<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteLine extends Model
{
    protected $fillable = [
        'credit_note_id', 'ingredient_id', 'asset_id', 'description',
        'quantity', 'uom_id', 'unit_price', 'total_price', 'reason_code',
    ];

    protected $casts = [
        'quantity'    => 'decimal:4',
        'unit_price'  => 'decimal:4',
        'total_price' => 'decimal:4',
    ];

    public function creditNote(): BelongsTo { return $this->belongsTo(CreditNote::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function uom(): BelongsTo { return $this->belongsTo(UnitOfMeasure::class, 'uom_id'); }

    /** An asset line, not an ingredient line. The id is the fact. */
    public function isAssetItem(): bool
    {
        return $this->asset_id !== null;
    }

    /**
     * What to show, whichever kind of line it is.
     *
     * The description falls last: on a credit note it is a sentence about
     * what went wrong ("STAND MIXER — Damaged"), not the item's name.
     */
    public function displayName(): string
    {
        return $this->asset?->name
            ?? $this->ingredient?->name
            ?? $this->description
            ?? '—';
    }
}
