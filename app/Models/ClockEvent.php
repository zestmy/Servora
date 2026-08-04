<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single clock-in or clock-out punch, with every check that was run on it.
 *
 * @see \App\Services\Hr\ClockInService which is the only thing that should
 *      create these; the model deliberately carries no punch logic itself.
 */
class ClockEvent extends Model
{
    /**
     * A punch is an attendance record, and a late one is a payroll record —
     * it carries the ringgit taken off somebody's service charge. Deleting
     * one has to stop it counting without destroying the evidence that it
     * happened, so it soft deletes.
     *
     * This also does the arithmetic for free: LatePenalties::forPeriod()
     * reads this table live and drops only the CompanyScope, so the
     * SoftDeletingScope survives and a deleted punch leaves the service
     * charge, the review queue and the export together.
     */
    use SoftDeletes;

    public const TYPE_IN          = 'in';
    public const TYPE_OUT         = 'out';
    public const TYPE_BREAK_START = 'break_start';
    public const TYPE_BREAK_END   = 'break_end';

    /** The punches that open and close attendance, as opposed to breaks. */
    public const SHIFT_TYPES = [self::TYPE_IN, self::TYPE_OUT];

    public const STATUS_VERIFIED = 'verified';
    public const STATUS_FLAGGED  = 'flagged';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Why a punch was flagged. Stored as keys rather than sentences so the
     * review screen can translate them and so a report can count them.
     */
    public const FLAG_LABELS = [
        'outside_geofence' => 'Outside the outlet',
        'no_location'      => 'Location not shared',
        'weak_location'    => 'GPS fix too vague to trust',
        'no_outlet_fence'  => 'Outlet has no coordinates set',
        'face_mismatch'    => 'Face did not match',
        'no_face'          => 'No face captured',
        'not_enrolled'     => 'No enrolled face on file',
        'no_shift'         => 'No rostered shift',
        'too_early'        => 'Far earlier than the shift',
        'late'             => 'Late',
        'duplicate'        => 'Already clocked in',
        'no_open_punch'    => 'Clocked out without clocking in',
        'no_open_break'    => 'Break ended without starting one',
        'break_overrun'    => 'Break ran over the allowance',
    ];

    protected $fillable = [
        'company_id', 'outlet_id', 'employee_id', 'roster_entry_id', 'type',
        'work_date', 'happened_at', 'latitude', 'longitude', 'accuracy_m',
        'distance_m', 'within_geofence', 'face_distance', 'face_verified',
        'selfie_path', 'minutes_late', 'chargeable_late_minutes',
        'penalty_amount', 'status', 'flags', 'reason', 'reviewed_by',
        'reviewed_at', 'review_note', 'override_late_minutes', 'device_label',
        'user_agent', 'ip_address',
    ];

    // deleted_by is deliberately absent from $fillable. It records WHO took a
    // punch out of the payroll, so it is written by the delete path alone and
    // must never be settable through mass assignment.

    protected $casts = [
        'work_date'               => 'date',
        'happened_at'             => 'datetime',
        'reviewed_at'             => 'datetime',
        'latitude'                => 'decimal:7',
        'longitude'               => 'decimal:7',
        'accuracy_m'              => 'integer',
        'distance_m'              => 'integer',
        'within_geofence'         => 'boolean',
        'face_distance'           => 'decimal:4',
        'face_verified'           => 'boolean',
        'minutes_late'            => 'integer',
        'chargeable_late_minutes' => 'integer',
        'override_late_minutes'   => 'integer',
        'penalty_amount'          => 'decimal:2',
        'flags'                   => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function rosterEntry(): BelongsTo
    {
        return $this->belongsTo(RosterEntry::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Who deleted it, while it is deleted. Cleared on restore. */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Punches that still count.
     *
     * A rejected punch is kept for the audit trail but must never reach
     * payroll, the attendance grid, or the service charge deduction — every
     * consumer goes through this scope rather than filtering by hand, so
     * there is one place to be wrong.
     */
    public function scopeCounted(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_REJECTED);
    }

    public function scopeNeedingReview(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FLAGGED);
    }

    /** Lateness actually charged for: a manager's override wins if set. */
    public function effectiveLateMinutes(): int
    {
        return $this->override_late_minutes ?? $this->chargeable_late_minutes;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function needsReview(): bool
    {
        return $this->status === self::STATUS_FLAGGED;
    }

    /** Human-readable flag reasons, skipping any key we no longer know. */
    public function flagLabels(): array
    {
        return collect($this->flags ?? [])
            ->map(fn ($f) => self::FLAG_LABELS[$f] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /** Human label for the punch type, for lists and the review queue. */
    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_IN          => 'Clock in',
            self::TYPE_OUT         => 'Clock out',
            self::TYPE_BREAK_START => 'Break start',
            self::TYPE_BREAK_END   => 'Break end',
            default                => ucfirst((string) $this->type),
        };
    }

    public function isBreak(): bool
    {
        return in_array($this->type, [self::TYPE_BREAK_START, self::TYPE_BREAK_END], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_VERIFIED => 'Verified',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default               => 'Needs review',
        };
    }
}
