<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A non-conformance: one line an auditor marked NC. See the create migration.
 */
class AuditFinding extends Model
{
    public const SEVERITY_MAJOR = 'major';
    public const SEVERITY_MINOR = 'minor';

    public const STATUS_OPEN     = 'open';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'company_id', 'outlet_id', 'audit_id', 'audit_line_id', 'severity',
        'section_name', 'item_label', 'points_lost', 'description', 'status',
    ];

    protected $casts = [
        'points_lost' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        // Photos are files on disk; the rows cascade but the files would not.
        static::deleting(function (self $finding) {
            $finding->photos()->get()->each->delete();
        });
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(AuditLine::class, 'audit_line_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AuditFindingPhoto::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CorrectiveAction::class)->orderBy('id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_OPEN);
    }

    public function isMajor(): bool
    {
        return $this->severity === self::SEVERITY_MAJOR;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /**
     * A finding is resolved when it has at least one action and every action
     * is verified. No actions at all means nobody has taken it up yet.
     */
    public function refreshStatus(): void
    {
        $actions = $this->actions()->get(['status']);

        $resolved = $actions->isNotEmpty()
            && $actions->every(fn ($a) => $a->status === CorrectiveAction::STATUS_VERIFIED);

        $this->forceFill(['status' => $resolved ? self::STATUS_RESOLVED : self::STATUS_OPEN])->save();
    }
}
