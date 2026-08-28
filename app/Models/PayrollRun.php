<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One month's payroll for a company, optionally narrowed to a SEGMENT of it —
 * an outlet, a section, an employment status, or any combination.
 *
 * Segments exist because a company does not always pay everybody the same way
 * on the same day. Outsourced heads are settled to an agent against a contract
 * rate with no statutory contributions at all, so they belong on their own run
 * with their own reference rather than mixed into a batch whose bank file then
 * has to be split by hand.
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

    /**
     * The employment segments a run can be built for, beyond the plain
     * statuses on Employee::EMPLOYMENT_STATUSES.
     *
     * Same vocabulary as the Employees list filter, deliberately — somebody
     * who has filtered a list to "own staff only" and then runs payroll for
     * that segment should be choosing the same words in both places.
     */
    public const SEGMENT_NONE                = 'none';
    public const SEGMENT_EXCLUDE_OUTSOURCING = 'exclude_outsourcing';

    /**
     * Every value the employment_status column accepts, with the label the
     * screens show for it. Null (all staff) is not in here — it is the absence
     * of a segment rather than one of them.
     *
     * @return array<string, string>
     */
    public static function employmentSegments(): array
    {
        return [
            self::SEGMENT_EXCLUDE_OUTSOURCING => 'Own staff only (exclude outsourced)',
            self::SEGMENT_NONE                => 'No employment status recorded',
        ] + Employee::EMPLOYMENT_STATUSES;
    }

    /**
     * Narrow an employee query to one employment segment.
     *
     * The single implementation, used by the builder when it computes a run
     * and by the screens when they count who a run would cover. Two of these
     * drifting apart would show a count that the run then does not match.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyEmploymentStatus($query, ?string $segment)
    {
        return match (true) {
            $segment === null || $segment === '' => $query,
            $segment === self::SEGMENT_NONE      => $query->whereNull('employment_status'),
            // Staff with no status recorded are the company's own until
            // somebody says otherwise, so they belong in "exclude outsourced"
            // rather than falling out of both halves of the split.
            $segment === self::SEGMENT_EXCLUDE_OUTSOURCING => $query->where(function ($q) {
                $q->whereNull('employment_status')->orWhere('employment_status', '!=', 'outsourcing');
            }),
            default => $query->where('employment_status', $segment),
        };
    }

    /** The label for whatever segment this run was built for. */
    public function employmentSegmentLabel(): ?string
    {
        if (! $this->employment_status) {
            return null;
        }

        return static::employmentSegments()[$this->employment_status] ?? $this->employment_status;
    }

    /**
     * What this run covered, in one line: "IOI City · Kitchen · Outsourcing".
     *
     * Every part that was left open is simply absent rather than spelled out
     * as "all", so an ordinary company-wide run still reads "All outlets"
     * instead of a row of three qualifiers saying nothing was narrowed.
     */
    public function scopeLabel(): string
    {
        $parts = array_filter([
            $this->outlet?->name ?? 'All outlets',
            $this->section?->name,
            $this->employmentSegmentLabel(),
        ]);

        return implode(' · ', $parts);
    }

    /** True when the run paid a slice of the company rather than all of it. */
    public function isSegmented(): bool
    {
        return $this->section_id !== null || $this->employment_status !== null;
    }

    protected $fillable = [
        'company_id', 'outlet_id', 'section_id', 'employment_status',
        'period_month', 'period_start', 'period_end', 'status', 'reference',
        // Null on an ordinary run, which counts everything over period_start
        // to period_end. See Services\Payroll\RunPeriods.
        'attendance_from', 'attendance_to',
        'overtime_from', 'overtime_to',
        'service_charge_from', 'service_charge_to',
        'total_gross', 'total_service_charge', 'total_net', 'total_statutory_employee',
        'total_statutory_employer', 'total_employer_cost', 'employee_count',
        'generated_by', 'generated_at', 'approved_by', 'approved_at',
        'paid_at', 'payment_date', 'rate_snapshot', 'rates_were_confirmed', 'notes',
    ];

    protected $casts = [
        'period_month'             => 'date',
        'period_start'             => 'date',
        'period_end'               => 'date',
        'attendance_from'          => 'date',
        'attendance_to'            => 'date',
        'overtime_from'            => 'date',
        'overtime_to'              => 'date',
        'service_charge_from'      => 'date',
        'service_charge_to'        => 'date',
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

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
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

    /**
     * THE MIRROR OF settleOvertime(): hours this run had spoken for are free
     * again.
     *
     * Approving stamps every payroll-settled claim in the period as paid by
     * this run. Returning the run to a draft has to undo exactly that, or the
     * claims keep saying they were paid by a run that is no longer approved —
     * and TimeOffBalance reads paid_at as the thing that ends availability, so
     * the hours would stay unavailable with nothing paying for them.
     *
     * Scoped on paid_in_run_id rather than on the period, so it releases what
     * THIS run actually stamped and cannot reach a claim settled by another.
     */
    public function releaseOvertime(): int
    {
        return \App\Models\OvertimeClaim::withoutGlobalScopes()
            ->where('paid_in_run_id', $this->id)
            ->update([
                'paid_at'        => null,
                'paid_in_run_id' => null,
                'marked_paid_by' => null,
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

    /**
     * The dates this run counts each of its inputs over.
     *
     * An ordinary run counts everything over its own period and every one of
     * these resolves to it; the object exists so the three that CAN differ —
     * attendance, overtime, service charge — are asked for by name rather than
     * by whichever date pair happened to be in scope.
     *
     * Not memoised: a run's periods are edited on the same instance during a
     * rebuild, and a cached copy would answer with the range being replaced.
     */
    public function periods(): \App\Services\Payroll\RunPeriods
    {
        return \App\Services\Payroll\RunPeriods::fromRun($this);
    }

    /**
     * The range one input was counted over.
     *
     * WHAT THIS IS NOT FOR: who is on the run, dated allowances, part-month
     * proration and its s.60I divisor, the statutory as-of date, PCB
     * year-to-date. Those stay on period_start/period_end because they decide
     * what somebody is PAID rather than which days were counted — see the note
     * on RunPeriods.
     *
     * @param  string  $component  a RunPeriods::COMPONENTS value
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    public function periodFor(string $component): array
    {
        return $this->periods()->for($component);
    }

    /** Whether any input on this run departs from its own period. */
    public function hasComponentPeriods(): bool
    {
        return $this->periods()->hasAny();
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
     * was for; the count is of runs already in that month, which is zero for a
     * company that pays everybody in one batch and climbs with each further
     * segment — per-outlet, per-section, or the outsourced heads on their own.
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
