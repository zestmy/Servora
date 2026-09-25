<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recurring audit: this form, at this outlet, this often. See the migration.
 */
class AuditSchedule extends Model
{
    public const FREQUENCIES = [
        'weekly'      => 'Weekly',
        'monthly'     => 'Monthly',
        'quarterly'   => 'Quarterly',
        'half_yearly' => 'Every 6 months',
        'yearly'      => 'Yearly',
    ];

    /** "Due soon" on the strip means within this many days. */
    public const SOON_DAYS = 14;

    protected $fillable = [
        'company_id', 'audit_template_id', 'outlet_id', 'frequency', 'next_due_on',
        'assigned_user_id', 'last_audit_id', 'last_started_on', 'is_active', 'notes', 'created_by',
    ];

    protected $casts = [
        'next_due_on'     => 'date',
        'last_started_on' => 'date',
        'is_active'       => 'boolean',
    ];

    // Read before the row is re-fetched, a created model would otherwise say
    // it is inactive — and "never due" is the wrong default for a plan.
    protected $attributes = [
        'is_active' => true,
        'frequency' => 'quarterly',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AuditTemplate::class, 'audit_template_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function lastAudit(): BelongsTo
    {
        return $this->belongsTo(Audit::class, 'last_audit_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->active()->whereDate('next_due_on', '<', now()->toDateString());
    }

    public function scopeDueSoon(Builder $q): Builder
    {
        return $q->active()
            ->whereDate('next_due_on', '>=', now()->toDateString())
            ->whereDate('next_due_on', '<=', now()->addDays(self::SOON_DAYS)->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->is_active && $this->next_due_on->lt(Carbon::today());
    }

    public function isDueSoon(): bool
    {
        return $this->is_active
            && ! $this->isOverdue()
            && $this->next_due_on->lte(Carbon::today()->addDays(self::SOON_DAYS));
    }

    public function frequencyLabel(): string
    {
        return self::FREQUENCIES[$this->frequency] ?? ucfirst($this->frequency);
    }

    /**
     * The due date after this one.
     *
     * Rolled from the CURRENT due date, not from today: a quarterly audit due
     * 1 March and done on 20 March is next due 1 June, not 20 June. An audit
     * that is very late rolls until it lands in the future, so one missed
     * quarter does not leave the next one already overdue.
     */
    public function nextDueAfter(Carbon $from): Carbon
    {
        $next = $from->copy();

        do {
            $next = match ($this->frequency) {
                'weekly'      => $next->addWeek(),
                'monthly'     => $next->addMonthNoOverflow(),
                'half_yearly' => $next->addMonthsNoOverflow(6),
                'yearly'      => $next->addYearNoOverflow(),
                default       => $next->addMonthsNoOverflow(3),
            };
        } while ($next->lte(Carbon::today()));

        return $next;
    }
}
