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

        $employees = Employee::with(['outlet', 'section'])
            ->whereIn('outlet_id', $accessibleOutletIds ?: [0])
            ->when($outletId !== null, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('is_active', true)
            ->inListOrder()
            ->get();

        $codes = AttendanceCode::orderBy('sort_order')->orderBy('code')->get();

        $cellMap = AttendanceRecord::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$from, $to])
            ->get()
            ->mapWithKeys(fn ($r) => [$r->employee_id . ':' . $r->work_date->format('Y-m-d') => $r->attendance_code_id]);

        // The RM/point base is every active employee in scope, which is what
        // $employees already is here — passed explicitly so the intent is on
        // the page rather than relying on the default.
        $totalPoints = (float) $employees->sum(fn ($e) => max(0, (float) $e->service_points_entitlement));

        return ServiceChargePeriod::distribute(
            $row, $employees, $codes, $cellMap, 5.0, 10.0, $totalPoints,
            LatePenalties::forPeriod($companyId, $outletId, $from, $to),
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
