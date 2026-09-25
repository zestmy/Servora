<?php

namespace App\Models;

use App\Models\Concerns\PurgesStoredFiles;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * What the outlet is doing about a finding, and who owns it.
 *
 *   open → in_progress → done → verified
 *
 * `done` is the outlet's claim; `verified` is the auditor's confirmation and
 * the only state that resolves the finding. See the create migration.
 */
class CorrectiveAction extends Model
{
    use PurgesStoredFiles;

    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE        = 'done';
    public const STATUS_VERIFIED    = 'verified';

    public const STATUSES = [
        self::STATUS_OPEN        => 'Open',
        self::STATUS_IN_PROGRESS => 'In progress',
        self::STATUS_DONE        => 'Done',
        self::STATUS_VERIFIED    => 'Verified',
    ];

    protected $fillable = [
        'company_id', 'outlet_id', 'audit_finding_id', 'owner_employee_id', 'description',
        'due_date', 'status', 'completed_at', 'completion_note', 'evidence_path',
        'verified_by', 'verified_at', 'verification_note', 'created_by',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'datetime',
        'verified_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::deleted(fn (self $action) => $action->purgeOwnedFile('evidence_path'));

        // The finding's own status is a summary of its actions, so every
        // change to an action re-derives it — including the last one going.
        static::saved(fn (self $action) => $action->finding()->withoutGlobalScopes()->first()?->refreshStatus());
        static::deleted(fn (self $action) => $action->finding()->withoutGlobalScopes()->first()?->refreshStatus());
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class, 'audit_finding_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_employee_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOutstanding(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_DONE]);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS], true)
            && $this->due_date
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function evidenceUrl(): ?string
    {
        return $this->evidence_path ? Storage::disk('public')->url($this->evidence_path) : null;
    }
}
