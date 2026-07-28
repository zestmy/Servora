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
        'company_id', 'kitchen_id', 'name', 'code', 'description', 'video_url', 'category',
        'yield_quantity', 'yield_uom_id',
        'packaging_uom', 'per_carton_qty', 'carton_weight',
        'shelf_life_days', 'shelf_life_value', 'shelf_life_unit', 'storage_instruction',
        'storage_temperature', 'min_batch_size',
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

    public function steps(): HasMany
    {
        return $this->hasMany(ProductionRecipeStep::class)->orderBy('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductionRecipeImage::class)->orderBy('sort_order');
    }

    /** Human-readable shelf life, e.g. "3 Days" — null when not set. */
    public function shelfLifeLabel(): ?string
    {
        if (! $this->shelf_life_value || ! $this->shelf_life_unit) {
            // Fall back to the legacy day count
            return $this->shelf_life_days ? $this->shelf_life_days . ' Day' . ($this->shelf_life_days == 1 ? '' : 's') : null;
        }

        $value = rtrim(rtrim(number_format((float) $this->shelf_life_value, 2, '.', ''), '0'), '.');
        $unit  = Recipe::SHELF_LIFE_UNITS[$this->shelf_life_unit] ?? ucfirst($this->shelf_life_unit);
        if (abs((float) $this->shelf_life_value - 1.0) < 0.0001) {
            $unit = rtrim($unit, 's');
        }

        return "{$value} {$unit}";
    }

    /** Human-readable storing instruction, e.g. "Chill" — null when not set. */
    public function storageLabel(): ?string
    {
        return $this->storage_instruction
            ? (Recipe::STORAGE_OPTIONS[$this->storage_instruction] ?? ucfirst($this->storage_instruction))
            : null;
    }

    /**
     * Calculate costs from ingredient lines — UOM-aware: each line's cost is
     * converted to the line's unit via UomService (a kg-priced ingredient
     * used in grams costs 1/1000 per unit, not the raw purchase price).
     */
    public function calculateCosts(): void
    {
        $this->loadMissing('lines.ingredient.baseUom', 'lines.ingredient.uomConversions', 'lines.uom');

        $uomService = app(\App\Services\UomService::class);
        $rawCost = 0;
        foreach ($this->lines as $line) {
            $ingredient = $line->ingredient;
            $uom        = $line->uom;
            if (! $ingredient || ! $uom) continue;

            if ($ingredient->is_prep) {
                $costPerUom = $uomService->convertCost($ingredient, $uom);
            } else {
                // Pre-yield cost basis (purchase_price / pack_size), converted
                // to the line's unit — same basis as the prep item form.
                $originalCost = $ingredient->current_cost;
                $packSize = max((float) $ingredient->pack_size, 0.0001);
                $ingredient->current_cost = (float) $ingredient->purchase_price / $packSize;
                $costPerUom = $uomService->convertCost($ingredient, $uom);
                $ingredient->current_cost = $originalCost;
            }

            $wasteFactor = 1 + (floatval($line->waste_percentage) / 100);
            $rawCost += $costPerUom * floatval($line->quantity) * $wasteFactor;
        }

        $yield = max(floatval($this->yield_quantity), 0.0001);
        $costPerUnit = ($rawCost + floatval($this->packaging_cost_per_unit) + floatval($this->label_cost)) / $yield;

        $this->update([
            'raw_material_cost'   => round($rawCost, 4),
            'total_cost_per_unit' => round($costPerUnit, 4),
        ]);
    }
}
