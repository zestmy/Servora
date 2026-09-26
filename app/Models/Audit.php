<?php

namespace App\Models;

use App\Models\Concerns\PurgesStoredFiles;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * One audit of one outlet on one day — a snapshot of its template plus what
 * the auditor found. See the create migration for the status flow and why the
 * template is copied rather than referenced.
 *
 * Not AuditLog. That is the activity trail; this is a QA visit.
 */
class Audit extends Model
{
    use SoftDeletes, PurgesStoredFiles;

    public const STATUS_DRAFT        = 'draft';
    public const STATUS_SUBMITTED    = 'submitted';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_CLOSED       = 'closed';

    public const OUTCOME_PASS        = 'pass';
    public const OUTCOME_CONDITIONAL = 'conditional';
    public const OUTCOME_FAIL        = 'fail';

    public const OUTCOMES = [
        self::OUTCOME_PASS        => 'Pass',
        self::OUTCOME_CONDITIONAL => 'Conditional pass',
        self::OUTCOME_FAIL        => 'Fail',
    ];

    public const STATUSES = [
        self::STATUS_DRAFT        => 'Draft',
        self::STATUS_SUBMITTED    => 'Submitted',
        self::STATUS_ACKNOWLEDGED => 'Acknowledged',
        self::STATUS_CLOSED       => 'Closed',
    ];

    protected $fillable = [
        'company_id', 'outlet_id', 'audit_template_id', 'audit_schedule_id', 'template_name', 'template_code',
        'alt_language', 'template_version', 'reference_number', 'status', 'audit_date',
        'time_in', 'time_out', 'auditor_id', 'header_values', 'notes',
        'total_points', 'na_points', 'available_points', 'lost_points', 'penalty_points',
        'score_points', 'score_percent', 'finding_count', 'outcome', 'major_count', 'outcome_rules', 'outcome_reasons',
        'reaudit_due_on', 'reaudit_of_id',
        'submitted_at', 'requires_acknowledgement', 'acknowledged_at', 'acknowledged_by_name',
        'acknowledged_by_position', 'signature_path', 'closed_at', 'created_by',
    ];

    protected $casts = [
        'audit_date'               => 'date',
        'header_values'            => 'array',
        'total_points'             => 'integer',
        'na_points'                => 'integer',
        'available_points'         => 'integer',
        'lost_points'              => 'integer',
        'penalty_points'           => 'integer',
        'score_points'             => 'integer',
        'score_percent'            => 'decimal:2',
        'finding_count'            => 'integer',
        'major_count'              => 'integer',
        'outcome_rules'            => 'array',
        'outcome_reasons'          => 'array',
        'reaudit_due_on'           => 'date',
        'requires_acknowledgement' => 'boolean',
        'submitted_at'             => 'datetime',
        'acknowledged_at'          => 'datetime',
        'closed_at'                => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        // The signature is the outlet's sign-off; it goes with the record it
        // signed, and only when the record is really gone.
        static::forceDeleted(fn (self $audit) => $audit->purgeOwnedFile('signature_path'));
    }

    // ── Relations ────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AuditTemplate::class, 'audit_template_id');
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    /** The recurring plan this audit was started from, when it was. */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AuditSchedule::class, 'audit_schedule_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(AuditSection::class)->orderBy('sort_order');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AuditLine::class)->orderBy('sort_order');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class);
    }

    /** The conditional-pass audit this one is the re-audit of, if it is one. */
    public function reauditOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reaudit_of_id');
    }

    /** Follow-ups that name this audit. */
    public function reaudits(): HasMany
    {
        return $this->hasMany(self::class, 'reaudit_of_id');
    }

    /** The submitted follow-up, when there is one. */
    public function completedReaudit(): ?self
    {
        return $this->reaudits()->where('status', '!=', self::STATUS_DRAFT)->orderBy('audit_date')->first();
    }

    /** Conditional passes still waiting for their re-audit. */
    public function scopeReauditOutstanding(Builder $q): Builder
    {
        return $q->where('status', '!=', self::STATUS_DRAFT)
            ->where('outcome', self::OUTCOME_CONDITIONAL)
            ->whereNotNull('reaudit_due_on')
            ->whereDoesntHave('reaudits', fn ($r) => $r->where('status', '!=', self::STATUS_DRAFT));
    }

    public function scopeReauditOverdue(Builder $q): Builder
    {
        return $q->reauditOutstanding()->whereDate('reaudit_due_on', '<', now()->toDateString());
    }

    public function needsReaudit(): bool
    {
        return $this->outcome === self::OUTCOME_CONDITIONAL
            && $this->reaudit_due_on !== null
            && ! $this->isDraft()
            && ! $this->reaudits()->where('status', '!=', self::STATUS_DRAFT)->exists();
    }

    public function isReauditOverdue(): bool
    {
        return $this->needsReaudit() && $this->reaudit_due_on->lt(\Carbon\Carbon::today());
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    // ── State ────────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** Past draft: the scores are final and the findings are the record. */
    public function isSubmitted(): bool
    {
        return $this->status !== self::STATUS_DRAFT;
    }

    public function isAcknowledged(): bool
    {
        return in_array($this->status, [self::STATUS_ACKNOWLEDGED, self::STATUS_CLOSED], true);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function outcomeLabel(): ?string
    {
        return $this->outcome ? (self::OUTCOMES[$this->outcome] ?? ucfirst($this->outcome)) : null;
    }

    /** The rules this audit is graded under — its own snapshot, else the defaults. */
    public function outcomeRules(): array
    {
        return array_replace(\App\Services\Audits\AuditScoreService::DEFAULT_RULES, $this->outcome_rules ?? []);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function signatureUrl(): ?string
    {
        return $this->signature_path && Storage::disk('local')->exists($this->signature_path)
            ? route('audits.signature', $this->id)
            : null;
    }

    /** "ROSE · IOI City Mall · 24 Aug 2023" — for headings and PDF titles. */
    public function title(): string
    {
        return trim(($this->template_code ?: $this->template_name) . ' · '
            . ($this->outlet?->name ?? '') . ' · '
            . $this->audit_date?->format('j M Y'));
    }
}
