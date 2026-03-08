<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WastageRecordLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'wastage_record_id', 'ingredient_id', 'recipe_id', 'quantity', 'uom_id',
        'unit_cost', 'total_cost', 'reason',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    public function wastageRecord(): BelongsTo
    {
        return $this->belongsTo(WastageRecord::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
