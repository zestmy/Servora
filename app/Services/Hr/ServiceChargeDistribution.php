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
     * @return array|null  null when no pool has been saved for this exact
     *                     period and outlet — a pool is keyed on both.
     */
    public function forPeriod(int $companyId, array $accessibleOutletIds, Carbon $from, Carbon $to, ?int $outletId): ?array
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
            ->employedDuring($from->toDateString())
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
            $row, $employees, $codes, $cellMap, 5.0, 10.0, $totalPoints,
            // By EMPLOYEE, not by outlet. A redirected person's punches are
            // recorded at the outlet they actually work in, so an outlet-scoped
            // lookup would search KLCC for punches that only exist at IOI and
            // quietly return nothing — handing them a pool share with their
            // lateness charge silently dropped.
            LatePenalties::forEmployees($companyId, $employees->pluck('id')->all(), $from, $to),
        );
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
