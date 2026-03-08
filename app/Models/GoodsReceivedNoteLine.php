<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceivedNoteLine extends Model
{
    protected $fillable = [
        'goods_received_note_id', 'ingredient_id', 'expected_quantity',
        'received_quantity', 'uom_id', 'unit_cost', 'total_cost', 'condition',
    ];

    protected $casts = [
        'expected_quantity'  => 'decimal:4',
        'received_quantity'  => 'decimal:4',
        'unit_cost'          => 'decimal:4',
        'total_cost'         => 'decimal:4',
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
}
