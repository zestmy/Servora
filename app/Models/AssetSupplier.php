<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Who sells an asset and what they last charged. Mirrors SupplierIngredient. */
class AssetSupplier extends Model
{
    use HasFactory;

    protected $table = 'asset_suppliers';

    protected $fillable = ['supplier_id', 'asset_id', 'supplier_sku', 'last_cost', 'is_preferred'];

    protected $casts = [
        'is_preferred' => 'boolean',
        'last_cost'    => 'decimal:4',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
