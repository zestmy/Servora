<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabourCostAllowance extends Model
{
    protected $fillable = ['labour_cost_id', 'label', 'amount'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function labourCost(): BelongsTo
    {
        return $this->belongsTo(LabourCost::class);
    }
}
