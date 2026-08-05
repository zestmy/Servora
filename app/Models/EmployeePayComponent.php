<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One allowance or deduction assigned to one employee, for a date range.
 *
 * Dated rather than a single current row: a summary for March has to report
 * what March was owed, not what the employee happens to get today.
 */
class EmployeePayComponent extends Model
{
    protected $fillable = [
        'company_id', 'employee_id', 'pay_component_id',
        'amount', 'effective_from', 'effective_to', 'notes',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class, 'pay_component_id');
    }

    /** In force at any point in [$from, $to] — an open end date never lapses. */
    public function scopeOverlapping($query, $from, $to)
    {
        return $query
            ->whereDate('effective_from', '<=', $to)
            ->where(function ($q) use ($from) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from);
            });
    }

    public function isCurrent(?Carbon $on = null): bool
    {
        $on ??= Carbon::today();

        return $this->effective_from->lte($on)
            && ($this->effective_to === null || $this->effective_to->gte($on));
    }
}
