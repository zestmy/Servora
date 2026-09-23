<?php

namespace App\Services\Hr;

use App\Models\LabourCostTransferLine;
use Illuminate\Support\Carbon;

/**
 * What confirmed labour cost transfers do to each outlet's labour cost over a
 * date range. The one place a labour report asks this question.
 *
 * ONLY CONFIRMED transfers count. A draft has not been agreed and a cancelled
 * one never will be.
 *
 * SPREAD BY CALENDAR DAY. A line's total is spread evenly over every day from
 * its start to its end, and a period takes the share of days that fall inside
 * it. A stint from 29 September to 2 October therefore puts 2/4 into
 * September and 2/4 into October, rather than all of it landing in whichever
 * month the paperwork happened to be dated. The line does not record WHICH
 * days were worked when days < the span, so an even spread is the honest
 * reading.
 *
 * The receiving outlet gains the cost (+), the employee's own outlet loses it
 * (−). Company-wide the two cancel, so a report over every outlet is
 * unchanged in total; only the split between outlets moves.
 */
class LabourCostTransferLedger
{
    /**
     * @return array<int, array{in: float, out: float, net: float}>  keyed by outlet id
     */
    public static function byOutlet(int $companyId, string $from, string $to): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to   = Carbon::parse($to)->startOfDay();

        $lines = LabourCostTransferLine::query()
            ->join('labour_cost_transfers as t', 't.id', '=', 'labour_cost_transfer_lines.labour_cost_transfer_id')
            ->where('t.company_id', $companyId)
            ->where('t.status', 'confirmed')
            ->whereNull('t.deleted_at')
            ->whereDate('labour_cost_transfer_lines.date_start', '<=', $to->toDateString())
            ->whereDate('labour_cost_transfer_lines.date_end', '>=', $from->toDateString())
            ->get([
                'labour_cost_transfer_lines.date_start', 'labour_cost_transfer_lines.date_end',
                'labour_cost_transfer_lines.total_amount', 'labour_cost_transfer_lines.from_outlet_id',
                't.to_outlet_id',
            ]);

        $out = [];
        foreach ($lines as $l) {
            $start = Carbon::parse($l->date_start)->startOfDay();
            $end   = Carbon::parse($l->date_end)->startOfDay();
            $span  = (int) $start->diffInDays($end) + 1;

            $overlapStart = $start->max($from);
            $overlapEnd   = $end->min($to);
            if ($overlapEnd->lt($overlapStart) || $span < 1) continue;

            $share  = ((int) $overlapStart->diffInDays($overlapEnd) + 1) / $span;
            $amount = (float) $l->total_amount * $share;

            $receiver = (int) $l->to_outlet_id;
            $sender   = (int) $l->from_outlet_id;

            $out[$receiver] ??= ['in' => 0.0, 'out' => 0.0, 'net' => 0.0];
            $out[$sender]   ??= ['in' => 0.0, 'out' => 0.0, 'net' => 0.0];
            $out[$receiver]['in']  += $amount;
            $out[$sender]['out']   += $amount;
        }

        foreach ($out as &$o) {
            $o['in']  = round($o['in'], 2);
            $o['out'] = round($o['out'], 2);
            $o['net'] = round($o['in'] - $o['out'], 2);
        }
        unset($o);

        return $out;
    }

    /**
     * The net adjustment for a set of outlets combined (every outlet when the
     * list is empty — which is zero, give or take rounding, by construction).
     *
     * @param  array<int, int>  $outletIds
     */
    public static function netFor(int $companyId, string $from, string $to, array $outletIds = []): float
    {
        $sum = 0.0;
        foreach (self::byOutlet($companyId, $from, $to) as $outletId => $row) {
            if ($outletIds && ! in_array((int) $outletId, $outletIds, true)) continue;
            $sum += $row['net'];
        }

        return round($sum, 2);
    }
}
