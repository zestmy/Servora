<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hours away from work, taken against overtime the company has not paid.
 *
 * Requested in HOURS, not days: contracts here run 7.5, 9 and 11 hours, so a
 * "day off" is not one amount of overtime. The employee's own working day is
 * used only to SHOW what the hours come to in days.
 */
class TimeOffRequest extends Model
{
    use SoftDeletes;

    public const PENDING   = 'pending';
    public const APPROVED  = 'approved';
    public const REJECTED  = 'rejected';
    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::PENDING   => 'Pending',
        self::APPROVED  => 'Approved',
        self::REJECTED  => 'Rejected',
        self::CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'company_id', 'employee_id', 'off_date', 'hours', 'reason',
        'status', 'applied_by', 'approved_by', 'approved_at', 'decision_note',
    ];

    protected $casts = [
        'off_date'    => 'date',
        'hours'       => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(TimeOffAllocation::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Pending and approved both hold hours against the balance. */
    public function scopeCommitting($query)
    {
        return $query->whereIn('status', [self::PENDING, self::APPROVED]);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
