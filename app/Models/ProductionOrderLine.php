<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderLine extends Model
{
    protected $fillable = [
        'production_order_id', 'recipe_id', 'production_recipe_id',
        'planned_quantity', 'actual_quantity',
        'uom_id', 'unit_cost', 'to_outlet_id', 'status', 'notes',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:4',
        'actual_quantity'  => 'decimal:4',
        'unit_cost'        => 'decimal:4',
    ];

    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }
    public function recipe(): BelongsTo { return $this->belongsTo(Recipe::class); }
    public function productionRecipe(): BelongsTo { return $this->belongsTo(ProductionRecipe::class); }
    public function uom(): BelongsTo { return $this->belongsTo(UnitOfMeasure::class, 'uom_id'); }
    public function toOutlet(): BelongsTo { return $this->belongsTo(Outlet::class, 'to_outlet_id'); }
}
