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

    /**
     * How the punch arrived. A property of the punch, not a problem with it —
     * which is why it is a column and not a flag.
     */
    public const SOURCE_KIOSK  = 'kiosk';
    public const SOURCE_BYOD   = 'byod';
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_LABELS = [
        self::SOURCE_KIOSK  => 'Outlet kiosk',
        self::SOURCE_BYOD   => 'Own device',
        self::SOURCE_MANUAL => 'Entered by manager',
    ];

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
        // A kiosk punch where the camera never named anybody and the person
        // keyed their PIN instead. Kept apart from no_face because the punch
        // still carries a photograph, and telling a manager no face was
        // captured while showing them one sends them hunting a camera fault
        // that is not there.
        'pin_fallback'     => 'Identified by PIN, not face',
        'not_enrolled'     => 'No enrolled face on file',
        'no_shift'         => 'No rostered shift',
        'too_early'        => 'Far earlier than the shift',
        'late'             => 'Late',
        'duplicate'        => 'Already clocked in',
        'no_open_punch'    => 'Clocked out without clocking in',
        'no_open_break'    => 'Break ended without starting one',
        'break_overrun'    => 'Break ran over the allowance',
        // The face resolved to more than one person closely enough that
        // picking a winner would have been a guess. Identity came from the PIN
        // instead — rare, and worth a look when it happens, because two
        // colleagues the model cannot separate is a standing risk at that
        // outlet rather than a one-off.
        'face_ambiguous'     => 'Face matched more than one person',
        'byod_when_kiosk_up' => 'Used own device while the kiosk was online',
        'kiosk_down'         => 'Kiosk was offline',
    ];

    protected $fillable = [
        'company_id', 'outlet_id', 'employee_id', 'roster_entry_id', 'type',
        'source', 'clock_device_id',
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

    /** The kiosk this came from, when it came from one. */
    public function device(): BelongsTo
    {
        return $this->belongsTo(ClockDevice::class, 'clock_device_id');
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

    /**
     * Where this punch happened, in words.
     *
     * Derived from the outlet it was recorded against and the geofence result
     * already stored — no reverse geocoding, which would mean an API key and
     * sending staff coordinates to a third party to learn something the
     * outlet already tells us. "At Suria KLCC" or "230 m from Suria KLCC"
     * answers the question a manager is actually asking.
     */
    public function locationLabel(): ?string
    {
        /*
         * A kiosk answers this outright and answers it first, ahead of even a
         * resolved street address. The tablet is bolted to a counter at an
         * outlet somebody chose when they paired it — there are no
         * coordinates to geocode, and none would improve on knowing which
         * device recorded it.
         */
        if ($this->fromKiosk()) {
            return $this->device?->name
                ? 'At ' . ($this->outlet?->name ?? 'the outlet') . ' — ' . $this->device->name
                : 'At ' . ($this->outlet?->name ?? 'the outlet') . ' (kiosk)';
        }

        // A resolved street address is the better answer, so it wins when the
        // company has switched reverse geocoding on and one came back.
        if (filled($this->address)) {
            return $this->address;
        }

        $outlet = $this->outlet?->name;

        if (! $outlet) {
            return $this->latitude !== null ? 'Location recorded' : null;
        }
        if ($this->latitude === null) {
            return 'No location recorded';
        }
        if ($this->within_geofence) {
            return 'At ' . $outlet;
        }
        if ($this->distance_m !== null) {
            // Under a kilometre reads better in metres; past that, "1.4 km".
            $away = $this->distance_m >= 1000
                ? number_format($this->distance_m / 1000, 1) . ' km'
                : number_format($this->distance_m) . ' m';

            return $away . ' from ' . $outlet;
        }

        // Coordinates but no distance: the outlet has no geofence set, so
        // there is nothing to measure against. Say that rather than imply
        // the punch was somewhere it may not have been.
        return 'Near ' . $outlet . ' (no geofence set)';
    }

    /**
     * Where this punch happened RELATIVE TO THE OUTLET, regardless of whether
     * a street address was resolved.
     *
     * Shown alongside the address, because "12 Jalan Sultan" does not answer
     * the question a manager reviewing a flagged punch is asking, which is
     * whether the person was at work.
     */
    public function geofenceLabel(): ?string
    {
        $outlet = $this->outlet?->name;

        // A kiosk punch has no geofence result to report and needs none: the
        // device it came from is the answer, and locationLabel() already gives
        // it. Returning "no location recorded" here would read as a gap in the
        // evidence rather than a stronger kind of it.
        if ($this->fromKiosk()) {
            return null;
        }

        if (! $outlet || $this->latitude === null) {
            return null;
        }
        if ($this->within_geofence) {
            return 'At ' . $outlet;
        }
        if ($this->distance_m !== null) {
            $away = $this->distance_m >= 1000
                ? number_format($this->distance_m / 1000, 1) . ' km'
                : number_format($this->distance_m) . ' m';

            return $away . ' from ' . $outlet;
        }

        return 'Near ' . $outlet . ' (no geofence set)';
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

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? 'Own device';
    }

    /**
     * Where the punch was made, for the review queue.
     *
     * A kiosk names itself, because "Front counter iPad" answers the question
     * a manager has about a kiosk punch — which tablet — in a way that the
     * bare word "kiosk" does not.
     */
    public function sourceDetail(): string
    {
        if ($this->source !== self::SOURCE_KIOSK) {
            return $this->sourceLabel();
        }

        return $this->device?->name
            ? 'Kiosk — ' . $this->device->name
            : 'Outlet kiosk (device removed)';
    }

    public function fromKiosk(): bool
    {
        return $this->source === self::SOURCE_KIOSK;
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
