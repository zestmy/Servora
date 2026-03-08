<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletTransferLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_transfer_id', 'ingredient_id', 'quantity', 'uom_id', 'unit_cost',
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

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
