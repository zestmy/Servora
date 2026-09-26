<?php

namespace App\Services\Hr;

use App\Models\CompensationSetting;
use App\Models\Employee;
use App\Models\LabourCostTransfer;
use App\Models\LabourCostTransferLine;
use App\Models\OvertimeClaim;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Prices one employee's stint at another outlet — days x daily rate, hours x
 * hourly rate, or nothing but OT — plus the approved overtime they claimed
 * inside the same dates.
 *
 * The ONLY place a labour transfer line gets its money. The form previews
 * through it and save() re-runs it, so nothing a browser sends is ever stored
 * as a rate or an amount.
 *
 * Rates come from CompensationSetting, the same divisors and OT multipliers
 * payroll uses, so a transfer can never value a day or an OT hour differently
 * from the payslip that paid for it. OT is approved claims only, and time-off
 * settled claims are left out: those hours never cost the company money.
 */
class LabourCostTransferCalculator
{
    private CompensationSetting $settings;

    public function __construct(int $companyId)
    {
        $this->settings = CompensationSetting::forCompany($companyId);
    }

    /** Calendar days from start to end, both included. */
    public static function calendarDays(string $start, string $end): int
    {
        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->startOfDay();

        return $e->lt($s) ? 0 : (int) $s->diffInDays($e) + 1;
    }

    /**
     * Price one line on its basis:
     *
     *   daily   — days x daily rate (days capped at the calendar days in range)
     *   hourly  — hours x hourly rate (hours capped at 24 per calendar day)
     *   ot_only — no salary part at all
     *
     * and, on every basis, the approved payroll-settled OT claims in the dates,
     * each at the hourly rate x its type's multiplier. $excludeClaimIds are
     * claims another line already carries: an OT hour moves once, however many
     * hourly or OT-only lines touch its date.
     *
     * @param  array<int, int>  $excludeClaimIds
     * @return array{
     *   basis: string, days: float, hours: float, daily_rate: float, hourly_rate: float,
     *   salary_amount: float, ot_hours: float, ot_amount: float, total_amount: float,
     *   ot_claim_ids: array<int, int>, ot_claims: Collection, has_salary: bool
     * }
     */
    public function price(Employee $employee, string $start, string $end, ?float $days = null, string $basis = 'daily', ?float $hours = null, array $excludeClaimIds = []): array
    {
        $basis        = array_key_exists($basis, \App\Models\LabourCostTransferLine::BASES) ? $basis : 'daily';
        $calendarDays = self::calendarDays($start, $end);

        $days  = $basis === 'daily'
            ? ($days === null ? $calendarDays : max(0, min($days, $calendarDays)))
            : 0.0;
        $hours = $basis === 'hourly' ? max(0, min((float) $hours, $calendarDays * 24)) : 0.0;

        $dailyHours = $employee->daily_working_hours !== null ? (float) $employee->daily_working_hours : null;
        $salary     = $employee->basic_salary !== null ? (float) $employee->basic_salary : null;

        $dailyRate  = (float) ($this->settings->dailyRate($salary, $employee->pay_type, null, $dailyHours) ?? 0);
        $hourlyRate = (float) ($this->settings->hourlyRate($salary, $employee->pay_type, $dailyHours) ?? 0);

        $claims = $calendarDays > 0 ? $this->approvedClaims($employee->id, $start, $end) : collect();
        if ($excludeClaimIds) {
            $claims = $claims->reject(fn ($c) => in_array((int) $c->id, $excludeClaimIds, true))->values();
        }

        $otHours  = 0.0;
        $otAmount = 0.0;
        foreach ($claims as $claim) {
            $h         = (float) $claim->total_ot_hours;
            $otHours  += $h;
            // A custom OT type is its own rate, not a multiple of this one.
            $otAmount += $claim->hasFixedRate()
                ? $h * (float) $claim->ot_hourly_rate
                : $h * $hourlyRate * $this->settings->multiplierFor((string) $claim->ot_type);
        }

        $salaryAmount = round(match ($basis) {
            'hourly'  => $hours * $hourlyRate,
            'ot_only' => 0.0,
            default   => $days * $dailyRate,
        }, 2);
        $otAmount = round($otAmount, 2);

        return [
            'basis'         => $basis,
            'days'          => round($days, 2),
            'hours'         => round($hours, 2),
            'daily_rate'    => round($dailyRate, 4),
            'hourly_rate'   => round($hourlyRate, 4),
            'salary_amount' => $salaryAmount,
            'ot_hours'      => round($otHours, 2),
            'ot_amount'     => $otAmount,
            'total_amount'  => round($salaryAmount + $otAmount, 2),
            'ot_claim_ids'  => $claims->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'ot_claims'     => $claims,
            'has_salary'    => $salary !== null && $salary > 0,
        ];
    }

    /** Approved, payroll-settled OT claims for the employee inside the dates. */
    public function approvedClaims(int $employeeId, string $start, string $end): Collection
    {
        return OvertimeClaim::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('settlement', '!=', OvertimeClaim::SETTLE_TIME_OFF)
            // whereDate, not whereBetween on strings: a claim on the END date
            // must count, whatever time part the driver stores with it.
            ->whereDate('claim_date', '>=', Carbon::parse($start)->toDateString())
            ->whereDate('claim_date', '<=', Carbon::parse($end)->toDateString())
            ->orderBy('claim_date')
            ->get(['id', 'claim_date', 'total_ot_hours', 'ot_type', 'ot_hourly_rate']);
    }

    /**
     * Whether two lines for the same person over overlapping dates would
     * charge the same pay twice.
     *
     * A DAILY line moves the whole day, so it clashes with anything else on
     * those dates. Hourly and OT-only lines move part of a day, so they may
     * share dates (four hours at an event in the afternoon, OT at another
     * outlet that night); the overtime they would both see is de-duplicated
     * by claim instead — see usedClaimIds().
     */
    public static function basesClash(string $a, string $b): bool
    {
        return $a === 'daily' || $b === 'daily';
    }

    /**
     * Another live transfer that already moves this person's cost for any of
     * these dates in a way this basis cannot sit beside. Charging the same day
     * twice is the mistake this whole document exists to prevent, so it is
     * refused rather than warned about.
     */
    public static function overlapping(int $employeeId, string $start, string $end, ?int $exceptTransferId = null, string $basis = 'daily'): ?LabourCostTransferLine
    {
        return self::liveLinesOverlapping($employeeId, $start, $end, $exceptTransferId)
            ->when($basis !== 'daily', fn ($q) => $q->where('basis', 'daily'))
            ->first();
    }

    /**
     * OT claims already carried by another live transfer for these dates, so
     * an hourly or OT-only line does not move the same overtime again.
     *
     * @return array<int, int>
     */
    public static function usedClaimIds(int $employeeId, string $start, string $end, ?int $exceptTransferId = null): array
    {
        // get() first: pluck() on the query would hand back the raw JSON.
        return self::liveLinesOverlapping($employeeId, $start, $end, $exceptTransferId)
            ->get(['id', 'ot_claim_ids'])
            ->pluck('ot_claim_ids')
            ->flatten()
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private static function liveLinesOverlapping(int $employeeId, string $start, string $end, ?int $exceptTransferId)
    {
        return LabourCostTransferLine::with('transfer')
            ->where('employee_id', $employeeId)
            ->whereDate('date_start', '<=', $end)
            ->whereDate('date_end', '>=', $start)
            ->whereHas('transfer', fn ($q) => $q->where('status', '!=', 'cancelled')
                ->when($exceptTransferId, fn ($q) => $q->where('id', '!=', $exceptTransferId)));
    }

    /**
     * Cost moved per outlet: what each sending outlet gives up and what the
     * receiving outlet takes on. Sending outlets come out negative, the
     * receiver positive, and the whole thing nets to zero.
     *
     * Takes plain rows so the form (unsaved array lines) and the PDF / list
     * (saved models) summarise through the same arithmetic.
     *
     * @param  iterable<int, array{from_outlet_id:int, to_outlet_id:int, employee_id:?int, days:float, ot_hours:float, salary_amount:float, ot_amount:float, total_amount:float}>  $rows
     * @return array<int, array{outlet_id:int, staff:int, days:float, ot_hours:float, salary_out:float, ot_out:float, sent:float, received:float, net:float}>
     */
    public static function summaryByOutlet(iterable $rows): array
    {
        $blank = fn (int $id) => [
            'outlet_id' => $id, 'staff' => [], 'days' => 0.0, 'hours' => 0.0, 'ot_hours' => 0.0,
            'salary_out' => 0.0, 'ot_out' => 0.0, 'sent' => 0.0, 'received' => 0.0, 'net' => 0.0,
        ];

        $out = [];
        foreach ($rows as $r) {
            $from = (int) $r['from_outlet_id'];
            $to   = (int) $r['to_outlet_id'];
            $out[$from] ??= $blank($from);
            $out[$to]   ??= $blank($to);

            // Counted once per person, however many stints they have here.
            $out[$from]['staff'][(string) ($r['employee_id'] ?? 'gone-' . count($out[$from]['staff']))] = true;
            $out[$from]['days']       += (float) $r['days'];
            $out[$from]['hours']      += (float) ($r['hours'] ?? 0);
            $out[$from]['ot_hours']   += (float) $r['ot_hours'];
            $out[$from]['salary_out'] += (float) $r['salary_amount'];
            $out[$from]['ot_out']     += (float) $r['ot_amount'];
            $out[$from]['sent']       += (float) $r['total_amount'];
            $out[$to]['received']     += (float) $r['total_amount'];
        }

        foreach ($out as &$o) {
            $o['staff']      = count($o['staff']);
            $o['days']       = round($o['days'], 2);
            $o['hours']      = round($o['hours'], 2);
            $o['ot_hours']   = round($o['ot_hours'], 2);
            $o['salary_out'] = round($o['salary_out'], 2);
            $o['ot_out']     = round($o['ot_out'], 2);
            $o['sent']       = round($o['sent'], 2);
            $o['received']   = round($o['received'], 2);
            $o['net']        = round($o['received'] - $o['sent'], 2);
        }
        unset($o);

        // Biggest movement first, receivers before senders at equal size.
        usort($out, fn ($a, $b) => abs($b['net']) <=> abs($a['net']) ?: $b['net'] <=> $a['net']);

        return array_values($out);
    }

    /** Rows for summaryByOutlet() from saved transfers, lines loaded. */
    public static function rowsFromTransfers(iterable $transfers): array
    {
        $rows = [];
        foreach ($transfers as $t) {
            /** @var LabourCostTransfer $t */
            foreach ($t->lines as $l) {
                $rows[] = [
                    'from_outlet_id' => $l->from_outlet_id,
                    'to_outlet_id'   => $t->to_outlet_id,
                    'employee_id'    => $l->employee_id,
                    'days'           => (float) $l->days,
                    'hours'          => (float) $l->hours,
                    'ot_hours'       => (float) $l->ot_hours,
                    'salary_amount'  => (float) $l->salary_amount,
                    'ot_amount'      => (float) $l->ot_amount,
                    'total_amount'   => (float) $l->total_amount,
                ];
            }
        }

        return $rows;
    }
}
