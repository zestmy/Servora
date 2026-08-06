<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One month's payroll for a company (optionally one outlet).
 *
 * DRAFT is the only state in which anything can change. Approving locks the
 * lines, because the figures stop being a working estimate and become what
 * the company owes; PAID records that the money actually left. Regenerating a
 * draft is free and expected — payroll is usually run once to look at, then
 * again after the last claims are approved.
 */
class PayrollRun extends Model
{
    public const DRAFT    = 'draft';
    public const APPROVED = 'approved';
    public const PAID     = 'paid';

    public const STATUSES = [
        self::DRAFT    => 'Draft',
        self::APPROVED => 'Approved',
        self::PAID     => 'Paid',
    ];

    protected $fillable = [
        'company_id', 'outlet_id', 'period_month', 'period_start', 'period_end', 'status', 'reference',
        'total_gross', 'total_service_charge', 'total_net', 'total_statutory_employee',
        'total_statutory_employer', 'total_employer_cost', 'employee_count',
        'generated_by', 'generated_at', 'approved_by', 'approved_at',
        'paid_at', 'payment_date', 'rate_snapshot', 'rates_were_confirmed', 'notes',
    ];

    protected $casts = [
        'period_month'             => 'date',
        'period_start'             => 'date',
        'period_end'               => 'date',
        'generated_at'             => 'datetime',
        'approved_at'              => 'datetime',
        'paid_at'                  => 'datetime',
        'payment_date'             => 'date',
        'rate_snapshot'            => 'array',
        'rates_were_confirmed'     => 'boolean',
        'total_gross'              => 'decimal:2',
        'total_service_charge'     => 'decimal:2',
        'total_net'                => 'decimal:2',
        'total_statutory_employee' => 'decimal:2',
        'total_statutory_employer' => 'decimal:2',
        'total_employer_cost'      => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        // Assigned on create so a run never exists without one — the route key
        // cannot be null for a row someone is about to be sent a link to.
        static::creating(function (self $run) {
            $run->uuid = $run->uuid ?: (string) \Illuminate\Support\Str::uuid();
        });
    }

    /**
     * URLs carry the UUID, not the id.
     *
     * Defence in depth only: the permission middleware and the company check
     * are what actually stop someone reading a payroll run. This stops an
     * authorised link from advertising how many runs exist, and stops anyone
     * walking the set by counting.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollRunLine::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Mark the overtime this run pays as paid.
     *
     * Called on APPROVAL, not on "mark paid": approving locks the figures and
     * commits the company to those amounts, so from that moment the hours are
     * spoken for. Leaving them open until the money physically moved would let
     * an employee take as time off overtime that payroll had already promised
     * to pay them — the double count this whole mechanism exists to prevent.
     *
     * Claims already paid by an earlier run are left alone, so a re-approval
     * or an overlapping period cannot rewrite which run settled what.
     */
    public function settleOvertime(?int $userId = null): int
    {
        $employeeIds = $this->lines()->pluck('employee_id')->filter();

        if ($employeeIds->isEmpty() || ! $this->period_start || ! $this->period_end) {
            return 0;
        }

        return \App\Models\OvertimeClaim::withoutGlobalScopes()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            /*
             * Time-off claims are NEVER settled by a run, and this line is
             * load-bearing rather than tidy.
             *
             * This run did not pay them — CompensationSummary leaves them out
             * of ot_amount entirely — so stamping paid_at here would mark as
             * settled money that never moved. Worse, TimeOffBalance treats
             * paid_at as the thing that ends availability, so approving any
             * payroll run would silently wipe the whole time-off balance this
             * setting exists to build up. The employee would simply find their
             * hours gone, with a paid_at on the claim saying they had been
             * paid for them.
             */
            ->where('settlement', \App\Models\OvertimeClaim::SETTLE_PAYROLL)
            ->whereNull('paid_at')
            ->whereBetween('claim_date', [$this->period_start, $this->period_end])
            ->update([
                'paid_at'        => now(),
                'paid_in_run_id' => $this->id,
                'marked_paid_by' => $userId,
            ]);
    }

    /** Only a draft may be regenerated or deleted. */
    public function isEditable(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function isApproved(): bool
    {
        return in_array($this->status, [self::APPROVED, self::PAID], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function periodLabel(): string
    {
        return Carbon::parse($this->period_month)->format('F Y');
    }

    /**
     * The actual dates covered, e.g. "26 Jul – 25 Aug 2026".
     *
     * Shown wherever the month label alone would be ambiguous: with a
     * mid-month cycle, "August" is not the same thing as August.
     */
    public function rangeLabel(): string
    {
        if (! $this->period_start || ! $this->period_end) {
            return $this->periodLabel();
        }

        $from = Carbon::parse($this->period_start);
        $to   = Carbon::parse($this->period_end);

        return $from->format($from->year === $to->year ? 'j M' : 'j M Y')
            . ' – ' . $to->format('j M Y');
    }

    /** True when the range is not simply the calendar month it is labelled with. */
    public function hasCustomRange(): bool
    {
        if (! $this->period_start || ! $this->period_end) {
            return false;
        }

        $month = Carbon::parse($this->period_month);

        return ! Carbon::parse($this->period_start)->isSameDay($month->copy()->startOfMonth())
            || ! Carbon::parse($this->period_end)->isSameDay($month->copy()->endOfMonth());
    }

    /**
     * PR-2026-08-0001, unique within the company.
     *
     * Sequenced per month rather than globally so the reference says when it
     * was for; the count is of runs already in that month, which is normally
     * zero because of the one-run-per-month unique key and only non-zero for
     * per-outlet runs.
     */
    public static function nextReference(int $companyId, Carbon $month): string
    {
        $seq = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereYear('period_month', $month->year)
            ->whereMonth('period_month', $month->month)
            ->count() + 1;

        return sprintf('PR-%s-%04d', $month->format('Y-m'), $seq);
    }
}
