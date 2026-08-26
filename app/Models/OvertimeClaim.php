<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OvertimeClaim extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'outlet_id', 'submitted_by', 'employee_id',
        'claim_date', 'ot_time_start', 'ot_time_end', 'total_ot_hours',
        'ot_type', 'reason', 'status', 'settlement',
        'approved_by', 'approved_at', 'rejected_reason',
        'source', 'roster_entry_id',
        'paid_at', 'paid_in_run_id', 'marked_paid_by', 'hours_taken_off',
    ];

    protected $casts = [
        'claim_date'     => 'date',
        'total_ot_hours' => 'decimal:2',
        'approved_at'    => 'datetime',
        'paid_at'        => 'datetime',
        'hours_taken_off' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    /** How an approved claim is settled. See the settlement migration. */
    public const SETTLE_PAYROLL  = 'payroll';
    public const SETTLE_TIME_OFF = 'time_off';

    /**
     * Whether the company has paid this out.
     *
     * Unpaid approved overtime is what an employee may draw on as time off;
     * once payroll has committed to paying it, it is gone.
     */
    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /**
     * Settled as time off — never priced, never paid, never settled by a run.
     *
     * Because payroll leaves it alone, paid_at stays null for good, so the
     * hours remain available in the time-off balance until they are actually
     * taken. That permanence IS the feature.
     */
    public function isTimeOff(): bool
    {
        return $this->settlement === self::SETTLE_TIME_OFF;
    }

    public function settlementLabel(): string
    {
        return $this->isTimeOff() ? 'Time Off' : 'Payroll';
    }

    /** Hours still available to take as time off. */
    public function hoursAvailableForTimeOff(): float
    {
        if ($this->status !== 'approved' || $this->isPaid()) {
            return 0.0;
        }

        return round(max(0, (float) $this->total_ot_hours - (float) $this->hours_taken_off), 2);
    }

    public function paidInRun(): BelongsTo
    {
        return $this->belongsTo(\App\Models\PayrollRun::class, 'paid_in_run_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rosterEntry(): BelongsTo
    {
        return $this->belongsTo(RosterEntry::class, 'roster_entry_id');
    }

    public function isFromRoster(): bool
    {
        return $this->source === 'roster';
    }

    public function otTypeLabel(): string
    {
        return match ($this->ot_type) {
            'normal_day'     => 'Normal Day',
            'public_holiday' => 'Public Holiday',
            'rest_day'       => 'Rest Day',
            default          => ucfirst($this->ot_type),
        };
    }

    // ── Duplicate claims ──

    /**
     * Statuses that occupy an employee's date.
     *
     * Rejected is absent on purpose: a rejection is an instruction to fix the
     * claim and send it again, so treating it as a duplicate would make the
     * resubmission impossible and leave the person unpaid for hours they
     * actually worked. Soft-deleted rows are excluded by the model's own
     * scope, for the same reason — a deleted claim is not a claim.
     */
    public const BLOCKING_STATUSES = ['draft', 'submitted', 'approved'];

    /**
     * Claims already standing against this employee on this date.
     *
     * $exceptId is the claim being edited — a record is not its own duplicate.
     *
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public static function onSameDayAs(int $employeeId, string $claimDate, ?int $exceptId = null)
    {
        return static::query()
            ->where('employee_id', $employeeId)
            // Normalised, because callers hand this in as a form string, a
            // cast Carbon, or a full datetime, and whereDate() compares to
            // whatever it is given.
            ->whereDate('claim_date', \Carbon\Carbon::parse($claimDate)->toDateString())
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->when($exceptId, fn ($q, $id) => $q->whereKeyNot($id));
    }

    /**
     * The claim that stands in the way, or null when the date is free.
     *
     * One method rather than an exists() check because every caller wants to
     * SAY which claim it collided with — "already has a claim on that date" is
     * an error somebody has to go hunting to act on.
     */
    public static function duplicateFor(int $employeeId, string $claimDate, ?int $exceptId = null): ?self
    {
        return static::onSameDayAs($employeeId, $claimDate, $exceptId)
            ->orderBy('id')
            ->first();
    }

    /**
     * Employee/date pairs carrying more than one live claim, within a scope.
     *
     * For records that predate the gate: they were legal when they were
     * entered, so they are reported rather than repaired. Grouped on the date
     * COLUMN, not a formatted date — a raw DATE_FORMAT here would make the
     * screen untestable on anything but MySQL.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $scope
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function duplicateGroups($scope): \Illuminate\Support\Collection
    {
        return $scope
            ->clone()
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->reorder()
            ->groupBy('employee_id', 'claim_date')
            ->havingRaw('COUNT(*) > 1')
            ->get(['employee_id', 'claim_date', \Illuminate\Support\Facades\DB::raw('COUNT(*) as claim_count')]);
    }
}
