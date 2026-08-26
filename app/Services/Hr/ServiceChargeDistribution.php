<?php

namespace App\Services\Hr;

use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\ServiceChargePeriod;
use Carbon\Carbon;

/**
 * The service charge split for a saved period, over EVERY employee in scope.
 *
 * The attendance grid computes the same thing over the rows it is currently
 * showing, because that screen is "what you see is what you export". A payout
 * report is the other question — who gets paid — so it never applies the
 * grid's section, employment or search filters. Both go through
 * ServiceChargePeriod::distribute(), which is where the arithmetic lives.
 */
class ServiceChargeDistribution
{
    /**
     * Work the split out afresh and KEEP it on the period.
     *
     * This is what "Save & Calculate" now means. Before it, only the pool
     * amount was stored and the split was recomputed from current staff every
     * time anybody opened the screen — so a period calculated at RM556 a point
     * read RM574 the following day because one service point had left the
     * divisor in between. Nobody had touched the pool.
     *
     * Called explicitly and only from a button that says so. Everything else
     * reads the kept figures; see ServiceChargePeriod::distribute().
     *
     * @return array|null  the fresh distribution, or null if no pool exists.
     */
    public function freeze(int $companyId, array $accessibleOutletIds, Carbon $from, Carbon $to, ?int $outletId, ?int $userId): ?array
    {
        $computed = $this->forPeriod($companyId, $accessibleOutletIds, $from, $to, $outletId, recalculate: true);

        if ($computed === null) {
            return null;
        }

        $row = $computed['row'];

        $row->forceFill([
            'distribution'  => ServiceChargePeriod::snapshotOf($computed),
            'calculated_at' => now(),
            'calculated_by' => $userId,
        ])->save();

        return $computed;
    }

    /**
     * @param  bool  $recalculate  ignore a kept split and work it out again.
     * @return array|null  null when no pool has been saved for this exact
     *                     period and outlet — a pool is keyed on both.
     */
    public function forPeriod(int $companyId, array $accessibleOutletIds, Carbon $from, Carbon $to, ?int $outletId, bool $recalculate = false): ?array
    {
        $row = ServiceChargePeriod::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('outlet_id', $outletId)
            ->whereDate('period_from', $from)
            ->whereDate('period_to', $to)
            ->first();

        if (! $row) {
            return null;
        }

        // Active staff PLUS anyone who resigned during this pool's period —
        // the same rule the grid and the payout slips use. A resignation
        // deactivates the row, so filtering on is_active alone would drop a
        // leaver from the very pool they earned points in while the slips
        // linked from this page still paid them; worse, their points would
        // also leave $totalPoints below and the RM/point would not match.
        /*
         * Who this pool PAYS, which is not the same question as who works at
         * the outlet — see Employee::scopeForServiceChargeOutlet(). Somebody
         * posted to IOI and redirected to KLCC appears in KLCC's payout and
         * not in IOI's.
         *
         * The accessible-outlet filter deliberately allows either end: a
         * manager who can see this pool must be able to see everyone it pays,
         * including somebody posted to a branch they do not otherwise have
         * access to. Withholding the row would show a payout whose figures do
         * not add up to the rows above them.
         */
        $employees = Employee::with(['outlet', 'section', 'serviceChargeOutlet'])
            ->where(function ($q) use ($accessibleOutletIds, $outletId) {
                $q->whereIn('outlet_id', $accessibleOutletIds ?: [0])
                    ->when($outletId !== null, fn ($q) => $q->orWhere('service_charge_outlet_id', $outletId));
            })
            ->forServiceChargeOutlet($outletId)
            ->employedDuring($from->toDateString(), $to->toDateString())
            ->inListOrder()
            ->get();

        $codes = AttendanceCode::orderBy('sort_order')->orderBy('code')->get();

        $cellMap = AttendanceRecord::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$from, $to])
            ->get()
            ->mapWithKeys(fn ($r) => [$r->employee_id . ':' . $r->work_date->format('Y-m-d') => $r->attendance_code_id]);

        // The RM/point base is everyone who was employed during the period,
        // which is what $employees already is here — passed explicitly so the
        // intent is on the page rather than relying on the default.
        // Anyone excluded from this pool takes no share, so their points must
        // leave the divisor too — otherwise the pool under-allocates.
        // $employees is already scoped to this pool's members, so the divisor
        // and the paid rows are the same set by construction — which is the
        // property that keeps RM/point honest.
        $totalPoints = (float) $employees
            ->reject(fn ($e) => $row->excludes($e->id))
            ->sum(fn ($e) => max(0, (float) $e->service_points_entitlement));

        return ServiceChargePeriod::distribute(
            $row, $outletId, $employees, $codes, $cellMap, 5.0, 10.0, $totalPoints,
            // By EMPLOYEE, not by outlet. A redirected person's punches are
            // recorded at the outlet they actually work in, so an outlet-scoped
            // lookup would search KLCC for punches that only exist at IOI and
            // quietly return nothing — handing them a pool share with their
            // lateness charge silently dropped.
            LatePenalties::forEmployees($companyId, $employees->pluck('id')->all(), $from, $to),
            $recalculate,
        );
    }

    /**
     * Every pool a PAYROLL RUN covers, distributed and merged.
     *
     * A run may be company-wide, and forPeriod() answers about ONE pool: it
     * matches on outlet_id, so a run with no outlet looked for a pool with no
     * outlet. Nobody saves one of those — the attendance grid keys a pool on
     * the outlet being viewed, so a company running two branches has two pools
     * and a company-wide run found neither. Payroll then paid no service
     * charge at all, silently, which on an F&B payroll is the largest line
     * after basic.
     *
     * POOLS ARE NEVER ADDED TOGETHER. Each is distributed within itself and
     * the ROWS are merged, because RM/point is a property of one pool: KLCC
     * collecting more than IOI is exactly what the split is meant to preserve,
     * and one combined divisor would move money between branches.
     *
     * @return array|null  null when the period has no pool at all.
     */
    public function forRun(int $companyId, array $accessibleOutletIds, Carbon $from, Carbon $to, ?int $outletId): ?array
    {
        $pools = ServiceChargePeriod::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('period_from', $from)
            ->whereDate('period_to', $to)
            ->get();

        if ($pools->isEmpty()) {
            return null;
        }

        /*
         * A company-wide pool, if somebody saved one from the All outlets
         * view, is the whole answer — its distribution already covers
         * everybody. Taking it AND the per-outlet pools would pay the same
         * people twice.
         */
        if ($pools->firstWhere('outlet_id', null)) {
            return $this->forPeriod($companyId, $accessibleOutletIds, $from, $to, null);
        }

        $poolOutletIds = $pools->pluck('outlet_id')->unique();

        if ($outletId !== null) {
            /*
             * A RUN FOR ONE OUTLET IS NOT THE SAME QUESTION AS ONE OUTLET'S
             * POOL.
             *
             * This used to return forPeriod() for the run's own outlet, which
             * dropped somebody's service charge between two runs. A person
             * posted to HQ and paid from KLCC's pool is on the HQ RUN — runs
             * are scoped by home outlet_id, and rightly so, because that is
             * where their salary is administered. But their share lives in
             * KLCC's distribution. The KLCC run held the row and correctly
             * dropped it (they are not on that run); the HQ run held the
             * person and asked HQ's pool, which by definition does not pay
             * them. Neither run paid it, and this is money on a payslip
             * rather than a page nobody printed.
             *
             * So: every pool that pays somebody POSTED here. Pools that pay
             * nobody on this run are left out, which is what keeps a
             * single-pool run reporting a single RM/point.
             *
             * Paying twice is prevented downstream rather than here, and
             * deliberately: the rows have to stay whole because RM/point is
             * the pool over EVERYBODY's points, so narrowing the distribution
             * would misprice the rate itself. PayrollRunBuilder drops the rows
             * for people who are not on the run, after the rate is fixed.
             */
            $paysThisOutlet = Employee::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('outlet_id', $outletId)
                ->get(['outlet_id', 'service_charge_outlet_id'])
                ->map(fn ($e) => $e->serviceChargeOutletId())
                ->unique();

            $poolOutletIds = $poolOutletIds->intersect($paysThisOutlet);
        }

        $merged  = [];
        $perPool = [];

        foreach ($poolOutletIds as $poolOutletId) {
            $result = $this->forPeriod($companyId, $accessibleOutletIds, $from, $to, (int) $poolOutletId);

            if (! $result) {
                continue;
            }

            $perPool[] = $result;

            foreach ($result['rows'] as $row) {
                $employeeId = $row['employee']->id;

                // An employee belongs to exactly one pool — the one named on
                // their record — so this cannot collide. Keyed rather than
                // appended so that if it ever does, it is one row and not two
                // payments.
                $merged[$employeeId] = $row + ['perPoint' => $result['perPoint']];
            }
        }

        if (! $merged) {
            return null;
        }

        $rows = array_values($merged);

        $sum = fn (string $key) => round(array_sum(array_map(
            fn ($r) => (float) ($r[$key] ?? 0), $rows
        )), 2);

        return [
            'rows'   => $rows,
            'totals' => [
                'gross'      => $sum('gross'),
                'deduction'  => $sum('dedAmt'),
                'lateAmt'    => $sum('lateAmt'),
                'lateMins'   => (int) array_sum(array_map(fn ($r) => (int) ($r['lateMins'] ?? 0), $rows)),
                'specialAmt' => $sum('specialAmt'),
                'net'        => $sum('net'),
            ],
            /*
             * No single RM/point across pools, and saying otherwise would be a
             * lie a payslip then prints. Each row carries its own; this is
             * null so a caller that wants one figure has to notice.
             */
            'perPoint'  => count($perPool) === 1 ? $perPool[0]['perPoint'] : null,
            'collected' => round(array_sum(array_map(fn ($p) => (float) $p['collected'], $perPool)), 2),
            'pools'     => $perPool,
        ];
    }

    /**
     * Saved pools the user can see, newest first — the report picks from
     * these rather than asking anyone to remember exact dates, because a pool
     * only exists for the exact from/to it was saved against.
     */
    public function savedPeriods(int $companyId, array $accessibleOutletIds, int $limit = 24)
    {
        return ServiceChargePeriod::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($accessibleOutletIds) {
                // A null outlet_id is the all-outlets pool.
                $q->whereNull('outlet_id')->orWhereIn('outlet_id', $accessibleOutletIds ?: [0]);
            })
            ->with('outlet:id,name')
            ->orderByDesc('period_from')
            ->limit($limit)
            ->get();
    }
}
