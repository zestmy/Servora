<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What one employee is entitled to, of one leave type, in one year. */
class LeaveEntitlement extends Model
{
    protected $fillable = [
        'company_id', 'employee_id', 'leave_type_id', 'year',
        'entitled_days', 'carried_days', 'adjustment_days', 'notes', 'granted_by',
    ];

    protected $casts = [
        'year'            => 'integer',
        'entitled_days'   => 'decimal:1',
        'carried_days'    => 'decimal:1',
        'adjustment_days' => 'decimal:1',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /** Everything available before anything is taken. */
    public function totalDays(): float
    {
        return round((float) $this->entitled_days + (float) $this->carried_days + (float) $this->adjustment_days, 1);
    }
}
