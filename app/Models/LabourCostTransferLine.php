<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabourCostTransferLine extends Model
{
    protected $fillable = [
        'labour_cost_transfer_id', 'employee_id', 'employee_name', 'from_outlet_id',
        'date_start', 'date_end', 'days', 'daily_rate', 'salary_amount',
        'ot_hours', 'ot_amount', 'total_amount', 'ot_claim_ids',
    ];

    protected $casts = [
        'date_start'    => 'date',
        'date_end'      => 'date',
        'days'          => 'decimal:2',
        'daily_rate'    => 'decimal:4',
        'salary_amount' => 'decimal:2',
        'ot_hours'      => 'decimal:2',
        'ot_amount'     => 'decimal:2',
        'total_amount'  => 'decimal:2',
        'ot_claim_ids'  => 'array',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(LabourCostTransfer::class, 'labour_cost_transfer_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function fromOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'from_outlet_id');
    }
}
