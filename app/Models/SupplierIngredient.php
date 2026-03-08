<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierIngredient extends Model
{
    use HasFactory;

    protected $table = 'supplier_ingredients';

    protected $fillable = [
        'supplier_id', 'ingredient_id', 'supplier_sku', 'last_cost', 'uom_id', 'is_preferred',
    ];

    protected $casts = [
        'is_preferred' => 'boolean',
        'last_cost' => 'decimal:4',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
