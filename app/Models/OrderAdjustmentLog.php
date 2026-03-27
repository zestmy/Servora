<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrderAdjustmentLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'adjustable_type', 'adjustable_id', 'field',
        'old_value', 'new_value', 'reason', 'adjusted_by', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function adjustable(): MorphTo
    {
        return $this->morphTo();
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
