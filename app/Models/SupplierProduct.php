<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'supplier_id', 'sku', 'name', 'description', 'category',
        'uom_id', 'pack_size', 'unit_price', 'currency',
        'min_order_quantity', 'lead_time_days', 'is_active',
        'previous_price', 'price_changed_at', 'price_change_percent',
    ];

    protected $casts = [
        'pack_size'            => 'decimal:4',
        'unit_price'           => 'decimal:4',
        'min_order_quantity'   => 'decimal:4',
        'is_active'            => 'boolean',
        'previous_price'       => 'decimal:4',
        'price_changed_at'     => 'datetime',
        'price_change_percent' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $product) {
            if ($product->isDirty('unit_price') && $product->getOriginal('unit_price') > 0) {
                $old = floatval($product->getOriginal('unit_price'));
                $new = floatval($product->unit_price);
                $product->previous_price = $old;
                $product->price_changed_at = now();
                $product->price_change_percent = round((($new - $old) / $old) * 100, 2);
            }
        });
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function uom(): BelongsTo { return $this->belongsTo(UnitOfMeasure::class, 'uom_id'); }

    public function mappings(): HasMany
    {
        return $this->hasMany(SupplierProductMapping::class);
    }

    public function scopeActive($query) { return $query->where('is_active', true); }
}
