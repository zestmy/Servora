<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_order_id', 'ingredient_id', 'ordered_quantity', 'delivered_quantity',
        'uom_id', 'unit_cost', 'condition',
    ];

    protected $casts = [
        'ordered_quantity' => 'decimal:4',
        'delivered_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
    ];

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
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
