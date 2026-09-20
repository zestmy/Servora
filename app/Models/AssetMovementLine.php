<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMovementLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_movement_id', 'asset_id', 'quantity', 'unit_cost', 'total_cost', 'notes',
    ];

    protected $casts = [
        'quantity'   => 'decimal:4',
        'unit_cost'  => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(AssetMovement::class, 'asset_movement_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
