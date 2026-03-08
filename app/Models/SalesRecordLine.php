<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesRecordLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_record_id', 'ingredient_category_id', 'recipe_id', 'sales_category_id',
        'item_name', 'quantity', 'unit_price', 'unit_cost', 'total_revenue', 'total_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_revenue' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    public function salesRecord(): BelongsTo
    {
        return $this->belongsTo(SalesRecord::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function salesCategory(): BelongsTo
    {
        return $this->belongsTo(SalesCategory::class);
    }

    public function ingredientCategory(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'ingredient_category_id');
    }
}
