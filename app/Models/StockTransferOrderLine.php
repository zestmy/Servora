<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferOrderLine extends Model
{
    protected $fillable = [
        'stock_transfer_order_id', 'ingredient_id', 'quantity',
        'uom_id', 'unit_cost', 'total_cost',
    ];

    protected $casts = [
        'quantity'   => 'decimal:4',
        'unit_cost'  => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    public function stockTransferOrder(): BelongsTo { return $this->belongsTo(StockTransferOrder::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function uom(): BelongsTo { return $this->belongsTo(UnitOfMeasure::class, 'uom_id'); }
}
