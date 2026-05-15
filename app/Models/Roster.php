<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Roster extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'company_id',
        'created_by',
        'last_edited_by',
        'last_edited_at',
        'outlet_id',
        'section_id',
        'week_start_date',
        'week_end_date',
        'status',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'notes',
        'revision',
        'revision_notes',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'last_edited_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RosterEntry::class);
    }

    public function dayRemarks(): HasMany
    {
        return $this->hasMany(RosterDayRemark::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(RosterAmendment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function submit(int $userId): void
    {
        $this->update([
            'status' => self::STATUS_SUBMITTED,
            'submitted_by' => $userId,
            'submitted_at' => now(),
        ]);
    }

    public function approve(int $userId): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        // Create pending OT claims for approved roster
        $this->createPendingOtClaims();
    }

    public function reject(int $userId, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function revertToDraft(): void
    {
        $this->update([
            'status' => self::STATUS_DRAFT,
            'submitted_by' => null,
            'submitted_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Increment revision number when amendments are made.
     */
    public function incrementRevision(string $notes = null): void
    {
        $this->update([
            'revision' => $this->revision + 1,
            'revision_notes' => $notes,
        ]);
    }

    /**
     * Create pending OT claims for all entries with planned OT.
     */
    public function createPendingOtClaims(): void
    {
        foreach ($this->entries()->where('planned_ot', '>', 0)->get() as $entry) {
            // Check if an OT claim already exists for this entry
            $existingClaim = OvertimeClaim::where('roster_entry_id', $entry->id)->first();
            if ($existingClaim) {
                continue;
            }

            OvertimeClaim::create([
                'company_id' => $this->company_id,
                'outlet_id' => $this->outlet_id,
                'employee_id' => $entry->employee_id,
                'claim_date' => $entry->day_date,
                'ot_time_start' => $entry->shift_start,
                'ot_time_end' => $entry->shift_end,
                'total_ot_hours' => $entry->planned_ot,
                'ot_type' => 'normal_day',
                'reason' => 'Planned OT from roster',
                'status' => 'pending',
                'source' => 'roster',
                'roster_entry_id' => $entry->id,
            ]);
        }
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_SUBMITTED => 'yellow',
            self::STATUS_APPROVED => 'green',
            self::STATUS_REJECTED => 'red',
            default => 'gray',
        };
    }

    /**
     * Get total hours worked for all entries.
     */
    public function getTotalHoursAttribute(): float
    {
        return (float) $this->entries->sum('hours_worked');
    }

    /**
     * Get total planned OT for all entries.
     */
    public function getTotalOtAttribute(): float
    {
        return (float) $this->entries->sum('planned_ot');
    }

    /**
     * Get unique employees assigned to this roster.
     */
    public function getAssignedEmployeesAttribute()
    {
        return Employee::whereIn('id', $this->entries->pluck('employee_id')->unique())->get();
    }
}
