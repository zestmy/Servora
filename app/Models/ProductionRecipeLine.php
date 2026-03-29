<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRecipeLine extends Model
{
    protected $fillable = [
        'production_recipe_id', 'ingredient_id', 'quantity',
        'uom_id', 'waste_percentage', 'sort_order',
    ];

    protected $casts = [
        'quantity'         => 'decimal:4',
        'waste_percentage' => 'decimal:2',
    ];

    public function productionRecipe(): BelongsTo { return $this->belongsTo(ProductionRecipe::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function uom(): BelongsTo { return $this->belongsTo(UnitOfMeasure::class, 'uom_id'); }
}
