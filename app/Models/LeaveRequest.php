<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One application for leave.
 *
 * `days` is stored rather than derived from the dates: a half day, a request
 * spanning a rest day, and a company that does not count weekends all make
 * "end minus start" the wrong answer. What was agreed is what is deducted.
 */
class LeaveRequest extends Model
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
        'company_id', 'employee_id', 'leave_type_id',
        'start_date', 'end_date', 'days', 'is_half_day', 'half_day_period',
        'reason', 'status', 'applied_by', 'approved_by', 'approved_at', 'decision_note',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'days'        => 'decimal:1',
        'is_half_day' => 'boolean',
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

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    /**
     * Counts against the balance while PENDING as well as approved.
     *
     * Someone with two days left who applies for two and then applies for two
     * more must be stopped at the second application, not at the second
     * approval — by then they may already have booked a flight.
     */
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

    /** The year the balance is drawn from — the date it STARTS. */
    public function balanceYear(): int
    {
        return (int) $this->start_date->format('Y');
    }
}
