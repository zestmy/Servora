<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * A requested change to an employee's basic salary, and its approval.
 *
 * The employee row is only written when an approved revision reaches its
 * effective date — see applyDue(). Until then the request is a record of
 * intent, so a raise agreed in advance cannot quietly take effect early.
 */
class SalaryRevision extends Model
{
    public const PENDING  = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = [
        'company_id', 'employee_id',
        'from_amount', 'from_pay_type', 'to_amount', 'to_pay_type',
        'effective_on', 'reason', 'status',
        'requested_by', 'reviewed_by', 'reviewed_at', 'review_note', 'applied_at',
    ];

    protected $casts = [
        'from_amount'  => 'decimal:2',
        'to_amount'    => 'decimal:2',
        'effective_on' => 'date',
        'reviewed_at'  => 'datetime',
        'applied_at'   => 'datetime',
    ];

    protected $attributes = [
        'status' => self::PENDING,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::PENDING);
    }

    /** Whether the current (or given) user may sign one of these off. */
    public static function canApprove($user = null): bool
    {
        $user ??= Auth::user();

        return (bool) $user?->can('hr.compensation.approve');
    }

    /**
     * Write an approved revision onto the employee once its date arrives.
     *
     * Called when a revision is approved and again by the scheduled sweep, so
     * a future-dated raise lands on the right day. applied_at is the guard
     * against writing the same revision twice.
     */
    public function applyIfDue(): bool
    {
        if ($this->status !== self::APPROVED || $this->applied_at !== null) {
            return false;
        }
        if ($this->effective_on->isFuture()) {
            return false;
        }

        $employee = Employee::withoutGlobalScopes()->find($this->employee_id);
        if (! $employee) {
            return false;
        }

        $employee->update([
            'basic_salary' => $this->to_amount,
            'pay_type'     => $this->to_pay_type,
        ]);

        $this->forceFill(['applied_at' => now()])->save();

        return true;
    }
}
