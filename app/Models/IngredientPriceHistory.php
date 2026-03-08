<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientPriceHistory extends Model
{
    use HasFactory;

    protected $table = 'ingredient_price_history';

    protected $fillable = [
        'ingredient_id', 'supplier_id', 'cost', 'uom_id', 'effective_date', 'source',
    ];

    protected $casts = [
        'cost' => 'decimal:4',
        'effective_date' => 'date',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
