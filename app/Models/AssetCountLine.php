<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetCountLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_count_id', 'asset_id', 'system_quantity', 'counted_quantity',
        'variance_quantity', 'unit_cost', 'line_value', 'variance_cost', 'notes',
    ];

    protected $casts = [
        'system_quantity'   => 'decimal:4',
        'counted_quantity'  => 'decimal:4',
        'variance_quantity' => 'decimal:4',
        'unit_cost'         => 'decimal:4',
        'line_value'        => 'decimal:4',
        'variance_cost'     => 'decimal:4',
    ];

    public function assetCount(): BelongsTo
    {
        return $this->belongsTo(AssetCount::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
