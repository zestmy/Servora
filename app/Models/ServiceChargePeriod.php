<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Service charge pool for one attendance period (company + optional outlet
 * + exact from/to dates), with the per-day deduction percentages used to
 * split it across employees by Service Points entitlement.
 */
class ServiceChargePeriod extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'period_from', 'period_to',
        'amount', 'retention_percent', 'mc_percent', 'abs_percent',
        'fund_allocations', 'special_deductions', 'excluded_employees',
    ];

    protected $casts = [
        'period_from'        => 'date',
        'period_to'          => 'date',
        'amount'             => 'decimal:2',
        'retention_percent'  => 'decimal:2',
        'mc_percent'         => 'decimal:2',
        'abs_percent'        => 'decimal:2',
        'fund_allocations'   => 'array',
        'special_deductions' => 'array',
        'excluded_employees' => 'array',
    ];

    /** MySQL does not read column defaults back after an insert. */
    protected $attributes = [
        'retention_percent' => 0,
    ];

    /** What is actually shared out, after the company's retention. */
    public function distributableAmount(): float
    {
        $retention = max(0.0, min(100.0, (float) $this->retention_percent));

        return round((float) $this->amount * (1 - $retention / 100), 2);
    }

    /** @return array<int, array{name: string, points: float}> */
    public function funds(): array
    {
        return collect($this->fund_allocations ?? [])
            ->map(fn ($f) => [
                'name'   => (string) ($f['name'] ?? ''),
                'points' => max(0.0, (float) ($f['points'] ?? 0)),
            ])
            ->filter(fn ($f) => $f['name'] !== '' && $f['points'] > 0)
            ->values()
            ->all();
    }

    /** A special deduction agreed for one employee this period. */
    public function specialDeductionFor(int $employeeId): array
    {
        $row = ($this->special_deductions ?? [])[(string) $employeeId]
            ?? ($this->special_deductions ?? [])[$employeeId]
            ?? null;

        return [
            'amount' => max(0.0, (float) ($row['amount'] ?? 0)),
            'note'   => (string) ($row['note'] ?? ''),
        ];
    }

    /**
     * Employees taking no share of THIS pool.
     *
     * A leaver is on the pool covering the period they worked because they
     * earned those points, but that is the default and not always the
     * agreement — so it can be overridden per person per pool. Stored against
     * the pool rather than on the employee, because it is a decision about one
     * period: excluding someone from June must not touch May.
     *
     * @return array<int, int>
     */
    public function excludedEmployeeIds(): array
    {
        return collect($this->excluded_employees ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function excludes(int $employeeId): bool
    {
        return in_array($employeeId, $this->excludedEmployeeIds(), true);
    }

    /**
     * Drop the excluded from an Employee query building the RM/point base.
     *
     * The base and the rows being paid must come from the same set of people:
     * points left in the divisor but not paid out UNDER-allocates the pool,
     * and points paid but missing from the divisor OVER-allocates it. Every
     * consumer of distribute() applies this to its base query, which is why
     * it lives here rather than being written out three times.
     */
    public function excludeFrom($query)
    {
        $ids = $this->excludedEmployeeIds();

        return $ids ? $query->whereNotIn('employees.id', $ids) : $query;
    }

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

    /**
     * Split a pool across employees by Service Points entitlement.
     *
     * RM/point = pool / total points; gross = points x RM/point; each
     * employee is deducted (MC days x mc%) + (absent days x abs%) of their
     * own gross, capped at 100%. MC days = cells marked with a code named
     * MC or SL, or whose label mentions "sick" (codes are per-company
     * configurable); absent days use the built-in Absent system code.
     * $cellMap is the grid's "empId:Y-m-d" => attendance_code_id map.
     * Fallback percents apply while no pool row exists yet ($row null).
     *
     * $totalPoints is the distribution base: the points sum of ALL active
     * employees in the outlet scope, NOT just the (possibly section/
     * employment/search-filtered) $employees rows being displayed —
     * filtering the grid must never inflate the RM/point value. Falls back
     * to summing $employees when not given.
     *
     * $latePenalties is the web clock-in's lateness charge, keyed by
     * employee_id — see LatePenalties::forPeriod(). It is a flat RM figure
     * rather than another percentage, because it is priced per MINUTE and a
     * percentage of a pool share that varies month to month would make the
     * same five minutes cost differently each time. It is subtracted AFTER
     * the day-based percentages and the result floored at zero: the service
     * charge can be reduced to nothing, but it is a share of a pool, not a
     * debt, and it must never invert into money owed.
     */
    public static function distribute(?self $row, $employees, $codes, $cellMap, float $mcPctFallback = 5.0, float $absPctFallback = 10.0, ?float $totalPoints = null, array $latePenalties = []): array
    {
        $mcCodeIds = $codes->filter(fn ($c) => in_array(strtoupper(trim($c->code)), ['MC', 'SL'], true)
                || stripos($c->label, 'sick') !== false)
            ->pluck('id')->all();
        $absentId = $codes->firstWhere('system_key', 'absent')?->id;

        $mcCounts  = [];
        $absCounts = [];
        foreach ($cellMap as $key => $codeId) {
            $empId = (int) strtok($key, ':');
            if (in_array($codeId, $mcCodeIds, true)) $mcCounts[$empId] = ($mcCounts[$empId] ?? 0) + 1;
            if ($codeId === $absentId)               $absCounts[$empId] = ($absCounts[$empId] ?? 0) + 1;
        }

        $staffPoints = $totalPoints
            ?? $employees->sum(fn ($e) => max(0, (float) $e->service_points_entitlement));

        // Funds take points alongside staff, so they dilute every share exactly
        // as another employee would — which is the point of expressing an
        // Outlet Fund in points rather than as a second percentage off the top.
        $funds      = $row ? $row->funds() : [];
        $fundPoints = array_sum(array_column($funds, 'points'));
        $totalPoints = $staffPoints + $fundPoints;

        // What is left after the company's retention is what gets shared.
        $collected     = $row ? (float) $row->amount : 0.0;
        $retentionPct  = $row ? max(0.0, min(100.0, (float) $row->retention_percent)) : 0.0;
        $distributable = $row ? $row->distributableAmount() : 0.0;
        $retentionAmt  = round($collected - $distributable, 2);

        // RM/point is rounded DOWN to a whole ringgit (e.g. 360.6130 -> 360);
        // the remainder stays undistributed.
        $perPoint = ($row && $totalPoints > 0) ? floor($distributable / $totalPoints) : 0.0;
        $mcPct    = $row ? (float) $row->mc_percent : $mcPctFallback;
        $absPct   = $row ? (float) $row->abs_percent : $absPctFallback;

        $fundRows = array_map(fn ($f) => $f + ['amount' => $f['points'] * $perPoint], $funds);

        $rows   = [];
        $totals = ['gross' => 0.0, 'deduction' => 0.0, 'lateAmt' => 0.0, 'lateMins' => 0,
                   'specialAmt' => 0.0, 'net' => 0.0];
        foreach ($employees as $emp) {
            // Excluded from this pool: no points, so no share and nothing to
            // deduct from. The row is still listed — a name that simply
            // vanished from the table would look like a bug, and "excluded"
            // is the answer to why the figure is zero.
            $excluded = $row ? $row->excludes($emp->id) : false;

            $points  = $excluded ? 0.0 : max(0, (float) $emp->service_points_entitlement);
            $mcDays  = $mcCounts[$emp->id] ?? 0;
            $absDays = $absCounts[$emp->id] ?? 0;
            // Zeroed when excluded: a "25%" against a nil gross reads as a
            // deduction that was applied, when nothing was.
            $dedPct  = $excluded ? 0.0 : min(100.0, $mcDays * $mcPct + $absDays * $absPct);
            $gross   = $points * $perPoint;
            $dedAmt  = $gross * $dedPct / 100;

            $late     = $latePenalties[$emp->id] ?? null;
            $lateMins = (int) ($late['minutes'] ?? 0);
            // Never more than what is left after the day-based deduction,
            // so the row's own net cannot go negative and drag the column
            // total below the pool that was actually paid out.
            $lateAmt  = min(max(0.0, $gross - $dedAmt), (float) ($late['amount'] ?? 0));

            // Agreed per employee for this period — a missed KPI, a till
            // shortfall. Last in the order and capped at what is left, for the
            // same reason as lateness: a share of a pool must never invert
            // into money owed.
            $special    = $row ? $row->specialDeductionFor($emp->id) : ['amount' => 0.0, 'note' => ''];
            $specialAmt = min(max(0.0, $gross - $dedAmt - $lateAmt), $special['amount']);

            $rows[] = [
                'employee'    => $emp,
                'excluded'    => $excluded,
                'points'      => $points,
                'mcDays'      => $mcDays,
                'absDays'     => $absDays,
                'dedPct'      => $dedPct,
                'gross'       => $gross,
                'dedAmt'      => $dedAmt,
                'lateMins'    => $lateMins,
                'lateAmt'     => $lateAmt,
                'specialAmt'  => $specialAmt,
                'specialNote' => $special['note'],
                'net'         => $gross - $dedAmt - $lateAmt - $specialAmt,
            ];
            $totals['gross']      += $gross;
            $totals['deduction']  += $dedAmt;
            $totals['lateAmt']    += $lateAmt;
            $totals['lateMins']   += $lateMins;
            $totals['specialAmt'] += $specialAmt;
            $totals['net']        += $gross - $dedAmt - $lateAmt - $specialAmt;
        }

        return [
            'row'           => $row,
            'rows'          => $rows,
            'totals'        => $totals,
            'staffPoints'   => $staffPoints,
            'fundPoints'    => $fundPoints,
            'totalPoints'   => $totalPoints,
            'funds'         => $fundRows,
            'collected'     => $collected,
            'retentionPct'  => $retentionPct,
            'retentionAmt'  => $retentionAmt,
            'distributable' => $distributable,
            // What the pool actually paid out, and what rounding left behind.
            'allocated'     => round($totals['net'] + array_sum(array_column($fundRows, 'amount')), 2),
            'perPoint'      => $perPoint,
            'mcPct'         => $mcPct,
            'absPct'        => $absPct,
            'hasLate'       => $totals['lateAmt'] > 0 || $totals['lateMins'] > 0,
            'hasSpecial'    => $totals['specialAmt'] > 0,
            'hasExcluded'   => collect($rows)->contains('excluded', true),
        ];
    }
}
