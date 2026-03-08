<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTakeLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_take_id', 'ingredient_id', 'system_quantity', 'actual_quantity',
        'variance_quantity', 'uom_id', 'unit_cost', 'variance_cost',
    ];

    protected $casts = [
        'system_quantity' => 'decimal:4',
        'actual_quantity' => 'decimal:4',
        'variance_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'variance_cost' => 'decimal:4',
    ];

    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
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
