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
        'amount', 'mc_percent', 'abs_percent',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to'   => 'date',
        'amount'      => 'decimal:2',
        'mc_percent'  => 'decimal:2',
        'abs_percent' => 'decimal:2',
    ];

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
     */
    public static function distribute(?self $row, $employees, $codes, $cellMap, float $mcPctFallback = 5.0, float $absPctFallback = 10.0): array
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

        $totalPoints = $employees->sum(fn ($e) => max(0, (float) $e->service_points_entitlement));
        $perPoint    = ($row && $totalPoints > 0) ? (float) $row->amount / $totalPoints : 0.0;
        $mcPct       = $row ? (float) $row->mc_percent : $mcPctFallback;
        $absPct      = $row ? (float) $row->abs_percent : $absPctFallback;

        $rows   = [];
        $totals = ['gross' => 0.0, 'deduction' => 0.0, 'net' => 0.0];
        foreach ($employees as $emp) {
            $points  = max(0, (float) $emp->service_points_entitlement);
            $mcDays  = $mcCounts[$emp->id] ?? 0;
            $absDays = $absCounts[$emp->id] ?? 0;
            $dedPct  = min(100.0, $mcDays * $mcPct + $absDays * $absPct);
            $gross   = $points * $perPoint;
            $dedAmt  = $gross * $dedPct / 100;

            $rows[] = [
                'employee' => $emp,
                'points'   => $points,
                'mcDays'   => $mcDays,
                'absDays'  => $absDays,
                'dedPct'   => $dedPct,
                'gross'    => $gross,
                'dedAmt'   => $dedAmt,
                'net'      => $gross - $dedAmt,
            ];
            $totals['gross']     += $gross;
            $totals['deduction'] += $dedAmt;
            $totals['net']       += $gross - $dedAmt;
        }

        return [
            'row'         => $row,
            'rows'        => $rows,
            'totals'      => $totals,
            'totalPoints' => $totalPoints,
            'perPoint'    => $perPoint,
            'mcPct'       => $mcPct,
            'absPct'      => $absPct,
        ];
    }
}
