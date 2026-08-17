<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One uploaded POS sales report, from arrival through application.
 *
 * The buffer between "a file arrived from a terminal" and "sales records
 * exist": the processor parses it, resolves outlets and department
 * mappings, and either applies it through ZeoniqSalesWriter or parks it
 * with a status a human can act on from the POS Sync screen.
 */
class PosSalesBatch extends Model
{
    public const STATUS_RECEIVED      = 'received';
    public const STATUS_PROCESSING    = 'processing';
    public const STATUS_APPLIED       = 'applied';
    public const STATUS_NEEDS_MAPPING = 'needs_mapping';
    public const STATUS_NEEDS_REVIEW  = 'needs_review';
    public const STATUS_FAILED        = 'failed';
    public const STATUS_SUPERSEDED    = 'superseded';

    /** Statuses the POS Sync screen surfaces for a human to act on. */
    public const ATTENTION_STATUSES = [
        self::STATUS_NEEDS_MAPPING,
        self::STATUS_NEEDS_REVIEW,
        self::STATUS_FAILED,
    ];

    /** Days the raw upload is kept on disk before the pruner drops it. */
    public const KEEP_FILES_DAYS = 30;

    /** Days a batch row that never applied is kept before pruning. */
    public const KEEP_UNAPPLIED_DAYS = 90;

    protected $fillable = [
        'company_id', 'pos_agent_id', 'outlet_id',
        'source_filename', 'sha256', 'file_path', 'report_type',
        'date_from', 'date_to', 'status',
        'parsed_summary', 'result', 'error',
        'processed_at', 'applied_at', 'applied_by',
    ];

    protected $casts = [
        'parsed_summary' => 'array',
        'result'         => 'array',
        'date_from'      => 'date',
        'date_to'        => 'date',
        'processed_at'   => 'datetime',
        'applied_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PosAgent::class, 'pos_agent_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function salesRecords(): HasMany
    {
        return $this->hasMany(SalesRecord::class);
    }

    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->whereIn('status', self::ATTENTION_STATUSES);
    }

    public function needsAttention(): bool
    {
        return in_array($this->status, self::ATTENTION_STATUSES, true);
    }

    public function isTerminalSuccess(): bool
    {
        return $this->status === self::STATUS_APPLIED
            || $this->status === self::STATUS_SUPERSEDED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_RECEIVED      => 'Received',
            self::STATUS_PROCESSING    => 'Processing',
            self::STATUS_APPLIED       => 'Applied',
            self::STATUS_NEEDS_MAPPING => 'Needs mapping',
            self::STATUS_NEEDS_REVIEW  => 'Needs review',
            self::STATUS_FAILED        => 'Failed',
            self::STATUS_SUPERSEDED    => 'Superseded',
            default                    => ucfirst($this->status),
        };
    }
}
