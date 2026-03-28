<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionLog extends Model
{
    protected $fillable = [
        'production_order_id', 'production_order_line_id', 'recipe_id',
        'batch_number', 'planned_yield', 'actual_yield', 'yield_variance_pct',
        'uom_id', 'total_cost', 'produced_by', 'produced_at', 'notes',
    ];

    protected $casts = [
        'planned_yield'      => 'decimal:4',
        'actual_yield'       => 'decimal:4',
        'yield_variance_pct' => 'decimal:2',
        'total_cost'         => 'decimal:4',
        'produced_at'        => 'datetime',
    ];

    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }
    public function productionOrderLine(): BelongsTo { return $this->belongsTo(ProductionOrderLine::class); }
    public function recipe(): BelongsTo { return $this->belongsTo(Recipe::class); }
    public function uom(): BelongsTo { return $this->belongsTo(UnitOfMeasure::class, 'uom_id'); }
    public function producedBy(): BelongsTo { return $this->belongsTo(User::class, 'produced_by'); }
}
