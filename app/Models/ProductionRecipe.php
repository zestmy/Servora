<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionRecipe extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'kitchen_id', 'name', 'code', 'description', 'category',
        'yield_quantity', 'yield_uom_id',
        'packaging_uom', 'per_carton_qty', 'carton_weight',
        'shelf_life_days', 'storage_temperature', 'min_batch_size',
        'packaging_cost_per_unit', 'label_cost', 'raw_material_cost',
        'total_cost_per_unit', 'selling_price_per_unit',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'yield_quantity'          => 'decimal:4',
        'carton_weight'           => 'decimal:2',
        'min_batch_size'          => 'decimal:4',
        'packaging_cost_per_unit' => 'decimal:4',
        'label_cost'              => 'decimal:4',
        'raw_material_cost'       => 'decimal:4',
        'total_cost_per_unit'     => 'decimal:4',
        'selling_price_per_unit'  => 'decimal:4',
        'is_active'               => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function kitchen(): BelongsTo { return $this->belongsTo(CentralKitchen::class, 'kitchen_id'); }
    public function yieldUom(): BelongsTo { return $this->belongsTo(UnitOfMeasure::class, 'yield_uom_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lines(): HasMany { return $this->hasMany(ProductionRecipeLine::class); }

    /**
     * Calculate costs from ingredient lines.
     */
    public function calculateCosts(): void
    {
        $this->loadMissing('lines.ingredient');

        $rawCost = 0;
        foreach ($this->lines as $line) {
            $ingredientCost = floatval($line->ingredient?->purchase_price ?? 0);
            $wasteFactor = 1 + (floatval($line->waste_percentage) / 100);
            $rawCost += $ingredientCost * floatval($line->quantity) * $wasteFactor;
        }

        $yield = max(floatval($this->yield_quantity), 0.0001);
        $costPerUnit = ($rawCost + floatval($this->packaging_cost_per_unit) + floatval($this->label_cost)) / $yield;

        $this->update([
            'raw_material_cost'   => round($rawCost, 4),
            'total_cost_per_unit' => round($costPerUnit, 4),
        ]);
    }
}
