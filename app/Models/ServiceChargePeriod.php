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
        'min_working_days',
        'fund_allocations', 'special_deductions', 'excluded_employees',
        'distribution', 'calculated_at', 'calculated_by',
    ];

    protected $casts = [
        'period_from'        => 'date',
        'period_to'          => 'date',
        'amount'             => 'decimal:2',
        'retention_percent'  => 'decimal:2',
        'mc_percent'         => 'decimal:2',
        'abs_percent'        => 'decimal:2',
        'min_working_days'   => 'integer',
        'fund_allocations'   => 'array',
        'special_deductions' => 'array',
        'excluded_employees' => 'array',
        'distribution'       => 'array',
        'calculated_at'      => 'datetime',
    ];

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    /** Whether this period's split has been calculated and kept. */
    public function isFrozen(): bool
    {
        return filled($this->distribution) && $this->calculated_at !== null;
    }

    /**
     * Reduce a live distribution to what is worth keeping.
     *
     * FIGURES, NOT PEOPLE. Rows are keyed by employee id and hold only money
     * and points; the name, section and outlet are looked up live when this is
     * rendered again. A rename should show the new name — what must not move
     * is what somebody was paid.
     *
     * @param  array<string, mixed>  $computed  the return of distribute()
     * @return array<string, mixed>
     */
    public static function snapshotOf(array $computed): array
    {
        return [
            'rows' => collect($computed['rows'])
                ->mapWithKeys(fn ($r) => [(string) $r['employee']->id => [
                    'excluded'     => (bool) $r['excluded'],
                    'elsewhere'    => (bool) $r['elsewhere'],
                    'points'       => (float) $r['points'],
                    'mcDays'       => (int) $r['mcDays'],
                    'absDays'      => (int) $r['absDays'],
                    'dedPct'       => (float) $r['dedPct'],
                    'gross'        => (float) $r['gross'],
                    'dedAmt'       => (float) $r['dedAmt'],
                    'lateMins'     => (int) $r['lateMins'],
                    'lateAmt'      => (float) $r['lateAmt'],
                    'specialAmt'   => (float) $r['specialAmt'],
                    'specialNote'  => $r['specialNote'],
                    'net'          => (float) $r['net'],
                    // Kept because it is the REASON a row is zero. Without it
                    // a period calculated under a 15-day minimum reads back
                    // as an unexplained exclusion once the days have been
                    // re-marked, and nobody can tell what it was judged on.
                    'workDays'     => (int) ($r['workDays'] ?? 0),
                    'belowMinDays' => (bool) ($r['belowMinDays'] ?? false),
                ]])
                ->all(),
            'totals'        => $computed['totals'],
            'staffPoints'   => $computed['staffPoints'],
            'fundPoints'    => $computed['fundPoints'],
            'totalPoints'   => $computed['totalPoints'],
            'funds'         => $computed['funds'],
            'collected'     => $computed['collected'],
            'retentionPct'  => $computed['retentionPct'],
            'retentionAmt'  => $computed['retentionAmt'],
            'distributable' => $computed['distributable'],
            'allocated'     => $computed['allocated'],
            'perPoint'      => $computed['perPoint'],
            'mcPct'         => $computed['mcPct'],
            'absPct'        => $computed['absPct'],
            'minDays'       => $computed['minDays'],
        ];
    }

    /**
     * The kept split, rebuilt in the shape distribute() returns.
     *
     * Every employee handed in gets a row, so the table looks the same as it
     * always did. Somebody who was not in the calculation — hired since, or
     * moved into this outlet since — gets a row of zeros flagged `notInPool`,
     * rather than being silently absent or, worse, quietly given a share of a
     * pool that was divided without them.
     *
     * The TOTALS come from the snapshot, never re-summed from the rows on
     * screen: they are what the pool actually paid, and a total that drifts
     * because the row list changed is the whole bug this exists to stop.
     *
     * @param  \Illuminate\Support\Collection  $employees
     * @return array<string, mixed>
     */
    public function frozenDistribution($employees): array
    {
        $snapshot = $this->distribution;
        $stored   = $snapshot['rows'] ?? [];

        $rows = $employees->map(function ($emp) use ($stored) {
            $r = $stored[(string) $emp->id] ?? null;

            if ($r === null) {
                return [
                    'employee' => $emp, 'excluded' => true, 'elsewhere' => false,
                    'points' => 0.0, 'mcDays' => 0, 'absDays' => 0, 'dedPct' => 0.0,
                    'gross' => 0.0, 'dedAmt' => 0.0, 'lateMins' => 0, 'lateAmt' => 0.0,
                    'specialAmt' => 0.0, 'specialNote' => null, 'net' => 0.0,
                    'workDays' => 0, 'belowMinDays' => false,
                    // The reason the row is empty, so a screen can say
                    // "not in this calculation" rather than "excluded".
                    'notInPool' => true,
                ];
            }

            // The defaults are for pools calculated before a minimum existed:
            // `+` keeps whatever the snapshot holds, so a period that WAS
            // judged on working days still reads back with its own figures.
            return $r + [
                'employee' => $emp, 'notInPool' => false,
                'workDays' => 0, 'belowMinDays' => false,
            ];
        })->values()->all();

        return [
            'row'           => $this,
            'rows'          => $rows,
            'totals'        => $snapshot['totals'],
            'staffPoints'   => $snapshot['staffPoints'],
            'fundPoints'    => $snapshot['fundPoints'],
            'totalPoints'   => $snapshot['totalPoints'],
            'funds'         => $snapshot['funds'],
            'collected'     => $snapshot['collected'],
            'retentionPct'  => $snapshot['retentionPct'],
            'retentionAmt'  => $snapshot['retentionAmt'],
            'distributable' => $snapshot['distributable'],
            'allocated'     => $snapshot['allocated'],
            'perPoint'      => $snapshot['perPoint'],
            'mcPct'         => $snapshot['mcPct'],
            'absPct'        => $snapshot['absPct'],
            'minDays'       => $snapshot['minDays'] ?? 0,
            'hasLate'       => ($snapshot['totals']['lateAmt'] ?? 0) > 0 || ($snapshot['totals']['lateMins'] ?? 0) > 0,
            'hasSpecial'    => ($snapshot['totals']['specialAmt'] ?? 0) > 0,
            'hasExcluded'   => collect($rows)->contains('excluded', true),
            'hasBelowMin'   => collect($rows)->contains('belowMinDays', true),
            // So a screen can say when it was fixed and by whom.
            'frozen'        => true,
            'calculatedAt'  => $this->calculated_at,
            'calculatedBy'  => $this->calculatedBy?->name,
        ];
    }

    /** MySQL does not read column defaults back after an insert. */
    protected $attributes = [
        'retention_percent' => 0,
        'min_working_days'  => 0,
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

    /**
     * Days somebody must have WORKED in this period to share the pool.
     *
     * Zero — the default, and what every pool saved before the column existed
     * holds — means no minimum, so the rule is off unless somebody sets it.
     */
    public function minWorkingDays(): int
    {
        return max(0, (int) $this->min_working_days);
    }

    /**
     * Working days per employee, counted off the attendance grid.
     *
     * WHAT COUNTS. A day counts when the cell carries a mark that says the
     * person was engaged that day. Three kinds of mark do not:
     *
     *  - UNRECORDED (UNR), and an empty cell, which is the same statement
     *    written two ways. UNR is how the grid says "nothing to record here" —
     *    the person had not started yet, or had already left. Counting it
     *    would hand a full month to somebody who worked three days of it,
     *    which is the exact case this whole option exists for.
     *  - DAY OFF. A rest day is not a working day in any month-end
     *    conversation, and counting it would let a rota with four offs a week
     *    clear a minimum nobody actually worked.
     *  - ABSENT. A day they did not turn up for is not a day worked. It also
     *    already carries its own per-day deduction, so it is never free.
     *
     * LEAVE COUNTS — annual, sick, public holiday, replacement. The person
     * was employed and entitled to be away; MC carries its own deduction and
     * losing the entire share on top of it would be the same day charged
     * twice. The minimum is a test of ENGAGEMENT over the period, not of
     * attendance within it.
     *
     * Codes are per-company configurable, so UNR is matched the way MC is in
     * distribute(): on the code itself, or on a label that says unrecorded.
     * Day off and absent are matched on their system keys, which cannot be
     * renamed out from under this.
     *
     * @param  \Illuminate\Support\Collection  $codes    every AttendanceCode
     * @param  iterable  $cellMap  "empId:Y-m-d" => attendance_code_id
     * @return array<int, int>  employee_id => working days
     */
    public static function workingDayCounts($codes, $cellMap): array
    {
        $notWorkedIds = $codes
            ->filter(fn ($c) => strtoupper(trim($c->code)) === 'UNR'
                || stripos($c->label, 'unrecorded') !== false
                || in_array($c->system_key, ['off', 'absent'], true))
            ->pluck('id')
            ->all();

        $counts = [];
        foreach ($cellMap as $key => $codeId) {
            if ($codeId === null || in_array($codeId, $notWorkedIds, true)) {
                continue;
            }
            $empId = (int) strtok($key, ':');
            $counts[$empId] = ($counts[$empId] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Employee ids that fall short of a minimum, out of a working-day count.
     *
     * Lives here so the ROWS and the DIVISOR are decided by one function. The
     * points of somebody who does not qualify have to leave the RM/point base
     * as well as the table — a share withheld while its points stay in the
     * divisor under-allocates the pool, and every consumer computes that base
     * its own way. Same failure the exclusions have, same fix.
     *
     * @param  \Illuminate\Support\Collection  $employees
     * @param  array<int, int>  $workDays  employee_id => working days
     * @return array<int, int>
     */
    public static function belowMinimumWorkingDays($employees, array $workDays, int $minDays): array
    {
        if ($minDays <= 0) {
            return [];
        }

        return $employees
            ->filter(fn ($e) => ($workDays[$e->id] ?? 0) < $minDays)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
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
     * $poolOutletId names which pool is being split, and is REQUIRED rather
     * than inferred from $row — null means the all-outlets pool, which is a
     * different statement from "no row saved yet". See the note at its use.
     *
     * $totalPoints is the distribution base: the points sum of ALL active
     * employees in the outlet scope, NOT just the (possibly section/
     * employment/search-filtered) $employees rows being displayed —
     * filtering the grid must never inflate the RM/point value. Falls back
     * to summing $employees when not given.
     *
     * $minDaysFallback is the qualifying period in WORKING DAYS, used only
     * while no pool row exists yet ($row null) — a saved pool carries its own,
     * exactly as the percentages do. Somebody below it takes no share and
     * their points leave the divisor with them; see workingDayCounts() for
     * what counts as a working day, and note that $totalPoints must already
     * have had them taken out of it by the caller that computed it.
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
    public static function distribute(?self $row, ?int $poolOutletId, $employees, $codes, $cellMap, float $mcPctFallback = 5.0, float $absPctFallback = 10.0, ?float $totalPoints = null, array $latePenalties = [], bool $recalculate = false, int $minDaysFallback = 0): array
    {
        /*
         * A CALCULATED PERIOD RETURNS WHAT IT WAS CALCULATED AS.
         *
         * This is the fix for a pool that re-priced itself after it had been
         * closed: only the amount was ever stored, so the split was recomputed
         * from current staff on every view and any edit to an employee moved
         * everybody's share. One point leaving the divisor took RM/point from
         * 556 to 574 on a period that had already been signed off.
         *
         * Placed here rather than at the three call sites — the grid, the
         * export and the payroll builder — so none of them can forget, and so
         * they cannot disagree with each other about what a period paid.
         *
         * $recalculate is the explicit way through, for the button that says
         * so. Nothing recalculates by accident.
         */
        if (! $recalculate && $row?->isFrozen()) {
            return $row->frozenDistribution($employees);
        }

        $mcCodeIds = $codes->filter(fn ($c) => in_array(strtoupper(trim($c->code)), ['MC', 'SL'], true)
                || stripos($c->label, 'sick') !== false)
            ->pluck('id')->all();
        $absentId = $codes->firstWhere('system_key', 'absent')?->id;

        // Days actually worked, for the qualifying minimum below. Counted
        // from the same $cellMap as MC and absence, so a period cannot be
        // judged on one reading of the grid and deducted on another.
        $workCounts = static::workingDayCounts($codes, $cellMap);
        $minDays    = $row ? $row->minWorkingDays() : max(0, $minDaysFallback);

        $mcCounts  = [];
        $absCounts = [];
        foreach ($cellMap as $key => $codeId) {
            $empId = (int) strtok($key, ':');
            if (in_array($codeId, $mcCodeIds, true)) $mcCounts[$empId] = ($mcCounts[$empId] ?? 0) + 1;
            if ($codeId === $absentId)               $absCounts[$empId] = ($absCounts[$empId] ?? 0) + 1;
        }

        // The fallback drops anyone short of the minimum, because they are
        // about to be paid nothing: points left in the divisor for a share
        // that is never handed out under-allocate the pool. Callers that work
        // the base out from the database pass $totalPoints and must take the
        // same people out there — see belowMinimumWorkingDays().
        $staffPoints = $totalPoints
            ?? $employees
                ->reject(fn ($e) => $minDays > 0 && ($workCounts[$e->id] ?? 0) < $minDays)
                ->sum(fn ($e) => max(0, (float) $e->service_points_entitlement));

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
        /*
         * Which pool this is — PASSED IN, never read off $row.
         *
         * It used to be $row?->outlet_id, which quietly conflated two different
         * things: "this is the all-outlets pool" and "this outlet's pool has no
         * amount saved yet". A pool is keyed on outlet AND period, so an outlet
         * nobody has typed a figure into yet has no row — and every redirected
         * employee then came back into it, because null means "everybody is in
         * by definition".
         *
         * On the CK attendance grid that showed somebody paid from KLCC as an
         * ordinary CK member, contradicting their own record. Worse, it
         * contradicted the DIVISOR on the same screen: serviceChargeTotalPoints()
         * has always scoped by the screen's outlet, so their points were out of
         * the base while their row was still taking a share — the pool
         * over-allocates, and the figures change again the moment somebody
         * saves and a row finally exists.
         */

        foreach ($employees as $emp) {
            /*
             * Paid from a DIFFERENT outlet's pool.
             *
             * Treated exactly like an exclusion — no points, no share, nothing
             * to deduct from — because that is arithmetically what it is here.
             * They are not missing money; they are collecting it from the
             * outlet named on their record, and counting them twice is the one
             * outcome this must never produce.
             *
             * It matters most in the attendance grid, where the rows are the
             * people who WORK at an outlet rather than the people its pool
             * pays. Somebody posted to IOI and paid from KLCC belongs in IOI's
             * attendance and in KLCC's payout, and this is what keeps those
             * two facts from contradicting each other on one screen.
             */
            $elsewhere = $poolOutletId !== null
                && (int) $emp->serviceChargeOutletId() !== (int) $poolOutletId;

            /*
             * Short of the qualifying period.
             *
             * The case this is for is the joiner who started on the 27th and
             * the leaver who went on the 3rd: service points are an
             * entitlement somebody holds whether or not they worked the
             * period, so without a minimum both take a FULL share of a month
             * they were barely in, out of the pockets of everyone who worked
             * it. Their days read UNR either side of their employment, which
             * is exactly what workingDayCounts() declines to count.
             *
             * Flagged separately from `excluded` for the same reason
             * `elsewhere` is: the screen has to be able to say WHY a row is
             * zero, and "excluded from this pool" is a decision somebody made
             * about one person, where this is a rule the whole pool was
             * calculated under.
             */
            $workDays     = $workCounts[$emp->id] ?? 0;
            $belowMinDays = $minDays > 0 && $workDays < $minDays;

            // Excluded from this pool: no points, so no share and nothing to
            // deduct from. The row is still listed — a name that simply
            // vanished from the table would look like a bug, and "excluded"
            // is the answer to why the figure is zero.
            $excluded = $elsewhere || $belowMinDays || ($row ? $row->excludes($emp->id) : false);

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
                'employee'     => $emp,
                'excluded'     => $excluded,
                // Kept separate from `excluded` even though it forces it, so a
                // screen can say "paid from KLCC" rather than the flatly
                // misleading "excluded from the service charge" — this person
                // is being paid, just not out of this pool.
                'elsewhere'    => $elsewhere,
                // Days worked and whether they cleared the minimum, so the
                // table can show the count it was judged on rather than
                // asking anybody to re-count the grid by eye.
                'workDays'     => $workDays,
                'belowMinDays' => $belowMinDays,
                'points'       => $points,
                'mcDays'       => $mcDays,
                'absDays'      => $absDays,
                'dedPct'       => $dedPct,
                'gross'        => $gross,
                'dedAmt'       => $dedAmt,
                'lateMins'     => $lateMins,
                'lateAmt'      => $lateAmt,
                'specialAmt'   => $specialAmt,
                'specialNote'  => $special['note'],
                'net'          => $gross - $dedAmt - $lateAmt - $specialAmt,
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
            'minDays'       => $minDays,
            'hasLate'       => $totals['lateAmt'] > 0 || $totals['lateMins'] > 0,
            'hasSpecial'    => $totals['specialAmt'] > 0,
            'hasExcluded'   => collect($rows)->contains('excluded', true),
            'hasBelowMin'   => collect($rows)->contains('belowMinDays', true),
        ];
    }
}
