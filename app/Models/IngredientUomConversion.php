<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientUomConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'ingredient_id', 'from_uom_id', 'to_uom_id', 'factor',
    ];

    protected $casts = [
        'factor'        => 'decimal:6',
        'from_uom_id'   => 'integer',
        'to_uom_id'     => 'integer',
        'ingredient_id' => 'integer',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function fromUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'from_uom_id');
    }

    public function toUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'to_uom_id');
    }
}
