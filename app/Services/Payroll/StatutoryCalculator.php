<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\EmployeeStatutoryProfile;
use App\Models\StatutorySetting;
use Carbon\Carbon;

/**
 * Malaysian statutory deductions for one employee-month: EPF, SOCSO, EIS, PCB.
 *
 * WHAT THIS IS, said plainly because money and a filing deadline depend on it:
 * a calculation from the rates held in StatutorySetting, which a human confirms
 * on the settings screen. It is NOT a certified payroll engine and it does not
 * know anything the rates do not say. Two gaps in particular:
 *
 *  1. EPF, SOCSO and EIS are published by KWSP and PERKESO as WAGE-BAND TABLES.
 *     This computes a percentage of the (capped) wage instead, which lands
 *     within a few sen of the table but is not guaranteed to equal it row for
 *     row. Reconcile against the official table before submitting a return.
 *  2. PCB uses the MTD formula from the Income Tax (Deduction from
 *     Remuneration) Rules — MTD = [(P − M) × R + B − (Z + X)] ÷ (n + 1) — fed
 *     with year-to-date remuneration, EPF and tax from COMMITTED payroll runs.
 *     With no committed run earlier in the year it estimates that history —
 *     the months already worked this year (from January, or from the join
 *     date) at this month's pay, with the PCB that pay would have attracted —
 *     so the deduction is the steady monthly figure. It used to treat those
 *     months as unpaid, which taxed only the rest of the year and deducted
 *     nothing at all for most staff of a company that started payroll here
 *     mid-year. The estimate is stated in the notes.
 *
 * Every figure this returns is labelled as an estimate in the UI for exactly
 * these reasons.
 */
class StatutoryCalculator
{
    /**
     * A month with no statutory contribution at all, in the shape for() always
     * returns. Every caller reads the same keys off every row, so "nothing is
     * switched on", "nobody is enrolled" and "this person is outsourced" have
     * to be a row of zeros rather than a missing row.
     */
    /**
     * Why an outsourced employee's contributions are all zero, written onto
     * the run line so a payslip carries the reason rather than leaving a
     * column of noughts to be interpreted.
     *
     * A constant because the line is later read BACK by
     * PayrollRunLine::isOutsourced() — that is the only record on a snapshot
     * of why this person was exempt, and matching it on a retyped string would
     * break the moment somebody improved the wording.
     */
    public const OUTSOURCED_NOTE = 'Outsourced — statutory contributions are the agent\'s, not this company\'s.';

    /** The same zeros, a different reason. See Employee::isIntern(). */
    public const INTERN_NOTE = 'Internship — no statutory contributions are made for this placement.';

    /**
     * PCB is on for the company but off on this person's statutory profile.
     * A constant because the run screen reads it back off the line to name
     * who is affected — a payslip with no PCB row otherwise gives no reason.
     */
    public const PCB_PROFILE_OFF_NOTE = 'PCB not deducted — switched off on this employee\'s statutory profile.';

    public const NONE = [
        'epf_employee' => 0.0, 'epf_employer' => 0.0,
        'socso_employee' => 0.0, 'socso_employer' => 0.0,
        'eis_employee' => 0.0, 'eis_employer' => 0.0,
        'pcb' => 0.0, 'hrdf_employer' => 0.0, 'zakat' => 0.0,
        'skbbk' => 0.0,
        'employee_total' => 0.0, 'employer_total' => 0.0,
        'notes' => [],
    ];

    public function __construct(
        private readonly StatutorySetting $settings,
    ) {}

    public static function forCompany(int $companyId): self
    {
        return new self(StatutorySetting::forCompany($companyId));
    }

    public function settings(): StatutorySetting
    {
        return $this->settings;
    }

    /**
     * @param  float  $epfWages    pay subject to EPF (basic + EPF-able allowances + OT)
     * @param  float  $socsoWages  pay subject to SOCSO and EIS
     * @param  float  $taxablePay  pay subject to income tax, for the PCB estimate
     * @return array{
     *     epf_employee: float, epf_employer: float,
     *     socso_employee: float, socso_employer: float,
     *     eis_employee: float, eis_employer: float,
     *     pcb: float,
     *     employee_total: float, employer_total: float,
     *     notes: array<int, string>,
     * }
     */
    public function for(
        Employee $employee,
        float $epfWages,
        float $socsoWages,
        float $taxablePay,
        ?Carbon $asOf = null,
        ?EmployeeStatutoryProfile $profile = null,
        ?array $ytd = null,
    ): array {
        $asOf    = $asOf ?? Carbon::today();

        /*
         * SOME STAFF CONTRIBUTE NOTHING, and this is checked before the
         * profile is even loaded. Two reasons, both in
         * Employee::hasNoStatutoryContribution():
         *
         *   OUTSOURCED — the company is not their employer of record. It buys
         *   their labour from an agent and settles the agent's invoice, so EPF,
         *   SOCSO, EIS, PCB and the HRD Corp levy are all obligations of an
         *   employer this company is not, and contributing would file it under
         *   a scheme number belonging to the agent.
         *
         *   INTERNSHIP — a placement paid by stipend, which this company has
         *   decided sits outside its contributions.
         *
         * Deliberately a status check and NOT a wipe of the employee's
         * statutory profile: somebody taken off the agency, or an intern hired
         * onto the books, gets their EPF number and settings back rather than
         * having them re-keyed from paperwork nobody kept.
         *
         * It overrides the per-employee switches rather than reading them,
         * because the switches answer "does this scheme apply to this person
         * here", and the answer is no regardless of what is ticked. The
         * employee form disables that section for the same reason.
         */
        if ($employee->hasNoStatutoryContribution()) {
            return array_merge(self::NONE, [
                'notes' => [$employee->isIntern() ? self::INTERN_NOTE : self::OUTSOURCED_NOTE],
            ]);
        }

        $profile = $profile ?? EmployeeStatutoryProfile::forEmployee($employee);
        $age     = $employee->date_of_birth?->diffInYears($asOf);

        $notes  = [];
        $result = [
            'epf_employee' => 0.0, 'epf_employer' => 0.0,
            'socso_employee' => 0.0, 'socso_employer' => 0.0,
            'eis_employee' => 0.0, 'eis_employer' => 0.0,
            'pcb' => 0.0,
            // Employer-only. Kept in the same array for one row shape, but it
            // must never reach employee_total — see the totals below.
            'hrdf_employer' => 0.0,
            // Deducted through payroll and reported in Part D of the EA form,
            // so it is returned rather than only used inside the PCB working.
            'zakat' => 0.0,
            // SKBBK / LINDUNG 24 Jam. Employee-only, so it has no employer
            // twin — the mirror image of hrdf_employer above.
            'skbbk' => 0.0,
        ];

        // Age decides the EPF, SOCSO and EIS rate. Without a date of birth the
        // under-60 rates are used and the row says so, rather than guessing
        // quietly in either direction.
        if ($age === null && ($this->settings->epf_enabled || $this->settings->socso_enabled)) {
            $notes[] = 'No date of birth on file — under-' . $this->settings->senior_age . ' rates assumed.';
        }
        $isSenior = $age !== null && $age >= $this->settings->senior_age;

        if ($this->settings->epf_enabled && $profile->epf_enabled) {
            [$result['epf_employee'], $result['epf_employer']] =
                $this->epf($epfWages, $isSenior, (bool) $profile->is_malaysian, $profile);
        }

        if ($this->settings->socso_enabled && $profile->socso_enabled) {
            [$result['socso_employee'], $result['socso_employer']] = $this->socso($socsoWages, $isSenior);
        }

        if ($this->settings->eis_enabled && $profile->eis_enabled) {
            if ($age !== null && $age >= $this->settings->eis_max_age) {
                $notes[] = 'EIS not contributed — at or over ' . $this->settings->eis_max_age . '.';
            } else {
                [$result['eis_employee'], $result['eis_employer']] = $this->eis($socsoWages);
            }
        }

        if ($this->settings->pcb_enabled && $profile->pcb_enabled) {
            $ytd = $ytd ?: YearToDate::NONE;

            // Months of this year already worked that no committed run covers:
            // estimate them rather than treating them as unpaid. See the class
            // note — unpaid is what zeroed PCB for a company new to payroll.
            // Only the GAP is estimated, so a company that started payroll here
            // in August keeps its real August and has January–July filled in.
            $monthsBefore    = $this->monthsEmployedBefore($employee, $asOf);
            $estimatedMonths = max(0, $monthsBefore - $ytd['months']);
            if ($estimatedMonths > 0) {
                $estimate = $this->estimatedYearToDate(
                    $taxablePay, $result['epf_employee'], $profile, $asOf, $estimatedMonths, $monthsBefore,
                );
                $ytd = [
                    'gross'  => $ytd['gross'] + $estimate['gross'],
                    'epf'    => $ytd['epf'] + $estimate['epf'],
                    'pcb'    => $ytd['pcb'] + $estimate['pcb'],
                    'zakat'  => $ytd['zakat'],
                    'months' => $ytd['months'] + $estimatedMonths,
                ];
            }

            ['mtd' => $result['pcb'], 'chargeable' => $chargeable] =
                $this->pcb($taxablePay, $result['epf_employee'], $profile, $asOf, $ytd);
            $result['zakat'] = round(max(0.0, (float) $profile->monthly_zakat), 2);

            // Which of the two it used matters to whoever checks the figure, so
            // the note says rather than describing PCB generically.
            if ($estimatedMonths > 0) {
                $notes[] = 'PCB estimated as if paid the same for the ' . $estimatedMonths . ' earlier month(s) of '
                    . $asOf->format('Y') . ' not covered by an approved payroll run. '
                    . 'Pay from before this company ran payroll here is not on record, so this is an estimate.';
            }

            // A zero PCB is a real answer below the tax threshold, but on a
            // payslip it is indistinguishable from PCB never having run. Say
            // which it is, with the figure that decided it.
            if ($result['pcb'] <= 0 && $taxablePay > 0) {
                $notes[] = 'No PCB this month — projected chargeable income of RM' . number_format($chargeable, 2)
                    . ' for ' . $asOf->format('Y') . ' is covered by the tax bands, rebate, zakat and PCB already deducted.';
            }
        } elseif ($this->settings->pcb_enabled && ! $profile->pcb_enabled) {
            $notes[] = self::PCB_PROFILE_OFF_NOTE;
        }

        /*
         * SKBBK — Skim Kemalangan Bukan Bencana Kerja, "LINDUNG 24 Jam".
         *
         * PERKESO cover for accidents OUTSIDE work, from 1 June 2026. The
         * EMPLOYEE PAYS ALL OF IT — PERKESO's wording is "caruman ditanggung
         * sepenuhnya oleh pekerja" — so there is no employer figure here and
         * nothing reaches employer_total. It is HRD Corp in reverse.
         *
         * WHO CONTRIBUTES is not a company-wide answer. A Cabinet decision on
         * 10 July 2026 let LOCAL employees opt out from 14 July by filing a
         * liability release; FOREIGN workers remain mandatory. So the default
         * is taken from the person, not the setting — see
         * EmployeeStatutoryProfile::contributesToSkbbk().
         *
         * A percentage of capped wages rather than the published band table,
         * the same approximation this class already makes for SOCSO and EIS
         * and warns about at the top. Reconcile against PERKESO before filing.
         */
        if ($this->settings->skbbk_enabled && $profile->contributesToSkbbk()) {
            $result['skbbk'] = $this->skbbk($socsoWages);

            if ($profile->skbbk_enabled === null && ! $profile->is_malaysian) {
                $notes[] = 'SKBBK deducted — mandatory for foreign employees.';
            }
        } elseif ($this->settings->skbbk_enabled && $profile->is_malaysian && $profile->skbbk_enabled === null) {
            // Said out loud rather than left as a silent zero: a local who
            // never opted in looks identical on a payslip to one who opted
            // out, and only one of those has a signed release behind it.
            $notes[] = 'SKBBK not deducted — voluntary for local employees and not opted in.';
        }

        // HRD Corp levy: employer only, charged on the employee's wages.
        // Malaysian employees only by default — the levy is on the local
        // workforce, and a foreign worker is normally outside it.
        if ($this->settings->hrdf_enabled && $profile->hrdf_enabled) {
            if (! $profile->is_malaysian) {
                $notes[] = 'HRD Corp levy not charged — not a Malaysian employee.';
            } else {
                $result['hrdf_employer'] = $this->hrdf($epfWages);
            }
        }

        // hrdf_employer is deliberately ABSENT from employee_total. The levy is
        // never deducted from anyone's pay, and a payslip that showed it as a
        // deduction would be wrong in the way people notice.
        // Zakat is NOT added here: it is already netted off inside the PCB
        // figure, and counting it again would deduct it twice.
        // SKBBK belongs here and nowhere else: it is deducted from the
        // employee and the employer contributes nothing towards it.
        $result['employee_total'] = round(
            $result['epf_employee'] + $result['socso_employee'] + $result['eis_employee']
            + $result['pcb'] + $result['skbbk'], 2
        );
        $result['employer_total'] = round(
            $result['epf_employer'] + $result['socso_employer'] + $result['eis_employer']
            + $result['hrdf_employer'], 2
        );
        $result['notes'] = $notes;

        return $result;
    }

    /**
     * EPF. The employer rate steps down above a wage threshold, both sides
     * drop at 60, and non-citizens contribute at their own (lower) rate.
     *
     * Rounded UP to the next ringgit, which is how KWSP's schedule behaves.
     *
     * @return array{0: float, 1: float} [employee, employer]
     */
    private function epf(float $wages, bool $isSenior, bool $isMalaysian, EmployeeStatutoryProfile $profile): array
    {
        if ($wages <= 0) {
            return [0.0, 0.0];
        }

        [$employeeRate, $employerRate] = match (true) {
            ! $isMalaysian => [
                (float) $this->settings->epf_employee_rate_foreign,
                (float) $this->settings->epf_employer_rate_foreign,
            ],
            $isSenior => [
                (float) $this->settings->epf_employee_rate_senior,
                (float) $this->settings->epf_employer_rate_senior,
            ],
            default => [
                (float) $this->settings->epf_employee_rate,
                $wages > (float) $this->settings->epf_wage_threshold
                    ? (float) $this->settings->epf_employer_rate_high
                    : (float) $this->settings->epf_employer_rate_low,
            ],
        };

        // An employee may elect to contribute above the statutory rate. It
        // never lowers it — that needs KWSP approval, not a form field.
        if ($profile->epf_employee_rate_override !== null) {
            $employeeRate = max($employeeRate, (float) $profile->epf_employee_rate_override);
        }

        return [
            $this->ceilRinggit($wages * $employeeRate / 100),
            $this->ceilRinggit($wages * $employerRate / 100),
        ];
    }

    /**
     * SOCSO, on wages capped at the insured ceiling. Over 60 (or first
     * registered after 55) is employer-only at the lower rate.
     *
     * @return array{0: float, 1: float} [employee, employer]
     */
    private function socso(float $wages, bool $isSenior): array
    {
        $insured = min(max($wages, 0), (float) $this->settings->socso_ceiling);
        if ($insured <= 0) {
            return [0.0, 0.0];
        }

        if ($isSenior) {
            return [0.0, round($insured * (float) $this->settings->socso_employer_rate_senior / 100, 2)];
        }

        return [
            round($insured * (float) $this->settings->socso_employee_rate / 100, 2),
            round($insured * (float) $this->settings->socso_employer_rate / 100, 2),
        ];
    }

    /**
     * SKBBK, on wages capped at its own ceiling. Employee side only.
     *
     * Its own ceiling rather than SOCSO's: they happen to both be RM6,000
     * today, and reusing one for the other would silently break the day
     * PERKESO moves either.
     */
    private function skbbk(float $wages): float
    {
        $insured = min(max($wages, 0), (float) $this->settings->skbbk_ceiling);

        return $insured <= 0
            ? 0.0
            : round($insured * (float) $this->settings->skbbk_employee_rate / 100, 2);
    }

    /** EIS, on the same capped wage, both sides at the same rate. */
    private function eis(float $wages): array
    {
        $insured = min(max($wages, 0), (float) $this->settings->eis_ceiling);
        if ($insured <= 0) {
            return [0.0, 0.0];
        }

        return [
            round($insured * (float) $this->settings->eis_employee_rate / 100, 2),
            round($insured * (float) $this->settings->eis_employer_rate / 100, 2),
        ];
    }

    /**
     * PCB by the LHDN MTD formula for normal remuneration:
     *
     *     MTD = [ (P − M) × R + B − (Z + X) ] ÷ (n + 1)
     *
     *   P  chargeable income for the year: pay already received, this month's
     *      pay, and the remaining months estimated at this month's pay, less
     *      EPF (capped annually) and the personal reliefs.
     *   M  the start of the tax band P falls in.
     *   R  that band's rate.
     *   B  tax on M, after the individual rebate where P is small enough.
     *   Z  zakat already paid this year, plus this month's.
     *   X  MTD already deducted this year.
     *   n  months remaining in the year after this one.
     *
     * The divisor (n + 1) is what makes this self-correcting: each month
     * spreads the remaining liability over the months left, so a raise or a
     * mid-year start is absorbed rather than compounding. In December n is 0
     * and the month settles whatever the year still owes.
     *
     * @param  array{gross: float, epf: float, pcb: float, zakat: float, months: int}  $ytd
     * @return array{mtd: float, chargeable: float}  the deduction, and the P it came from
     */
    private function pcb(
        float $taxablePay,
        float $epfEmployee,
        EmployeeStatutoryProfile $profile,
        Carbon $asOf,
        array $ytd,
    ): array {
        $n = YearToDate::remainingMonths($asOf);

        // P — the year's chargeable income as it currently looks.
        $futurePay = $taxablePay * $n;
        $annualPay = $ytd['gross'] + $taxablePay + $futurePay;

        // EPF relief is an ANNUAL cap, so the year's contributions — past,
        // present and projected — are capped together rather than each month
        // being allowed the full cap.
        $annualEpf = $ytd['epf'] + $epfEmployee + ($epfEmployee * $n);
        // (P − M) × R + B, from the same band table the annual calculation uses.
        ['tax' => $tax, 'chargeable' => $p] = $this->annualTax($annualPay, $annualEpf, $profile);

        if ($p <= 0) {
            return ['mtd' => 0.0, 'chargeable' => 0.0];
        }

        // Z — zakat paid this year including this month. Not stored per line,
        // so it is derived from the standing monthly figure and the months
        // already committed.
        $monthlyZakat = (float) $profile->monthly_zakat;
        $z = $monthlyZakat * ($ytd['months'] + 1);

        // X — MTD already deducted this year.
        $x = $ytd['pcb'];

        $mtd = ($tax - ($z + $x)) / ($n + 1);

        // Never negative: MTD is a deduction, and an over-deduction earlier in
        // the year is refunded on assessment, not paid back through payroll.
        return ['mtd' => round(max(0.0, $mtd), 2), 'chargeable' => round($p, 2)];
    }

    /**
     * (P − M) × R + B for a year's pay and EPF: the tax on the chargeable
     * income, after the rebate. Negative is possible (a rebate larger than the
     * tax) and is left for the caller, as the MTD formula expects.
     *
     * @return array{tax: float, chargeable: float}
     */
    private function annualTax(float $annualPay, float $annualEpf, EmployeeStatutoryProfile $profile): array
    {
        // EPF relief is an ANNUAL cap, so the year's contributions — past,
        // present and projected — are capped together rather than each month
        // being allowed the full cap.
        $epfRelief = min($annualEpf, (float) $this->settings->pcb_relief_epf_cap);

        $relief = (float) $this->settings->pcb_relief_individual
            + $epfRelief
            + ($profile->pcb_category === 'spouse_not_working' ? (float) $this->settings->pcb_relief_spouse : 0)
            + ($profile->children * (float) $this->settings->pcb_relief_child)
            + (float) $profile->annual_other_relief;

        $p = max(0.0, $annualPay - $relief);

        if ($p <= 0) {
            return ['tax' => 0.0, 'chargeable' => 0.0];
        }

        [$m, $r] = $this->bandFor($p);
        $b = $this->taxOn($m);

        // The individual rebate for a small chargeable income. Leaving it out
        // deducts tax from low-paid staff who owe none — the most visible way
        // this calculation can be wrong. A second rebate applies where the
        // spouse has no income.
        if ($p <= (float) $this->settings->pcb_rebate_threshold) {
            $rebate = (float) $this->settings->pcb_rebate_amount;
            if ($profile->pcb_category === 'spouse_not_working') {
                $rebate *= 2;
            }
            $b -= $rebate;
        }

        return ['tax' => ($p - $m) * $r + $b, 'chargeable' => $p];
    }

    /**
     * Whole months of this year the employee worked BEFORE $asOf's month:
     * from January, or from the month they joined if that was this year.
     * Zero in January and for anyone who joined this month.
     */
    private function monthsEmployedBefore(Employee $employee, Carbon $asOf): int
    {
        $monthStart = $asOf->copy()->startOfMonth();
        $from       = $asOf->copy()->startOfYear();

        if ($employee->join_date && $employee->join_date->gt($from)) {
            $from = $employee->join_date->copy()->startOfMonth();
        }

        return $from->gte($monthStart) ? 0 : (int) round($from->diffInMonths($monthStart));
    }

    /**
     * A stand-in for the history no committed run covers: $months earlier
     * months at this month's pay, EPF and the matching share of the year's tax.
     *
     * The PCB is spread over every month employed this year ($monthsBefore, the
     * real and estimated months together, plus this one and those left), so a
     * month with no real history gets that same even share — the steady
     * deduction — rather than dumping the earlier months' tax on the rest of
     * the year, or skipping it. Real months that deducted too little still
     * catch up, which is what the MTD formula is for.
     *
     * @return array{gross: float, epf: float, pcb: float, zakat: float, months: int}
     */
    private function estimatedYearToDate(
        float $taxablePay,
        float $epfEmployee,
        EmployeeStatutoryProfile $profile,
        Carbon $asOf,
        int $months,
        int $monthsBefore,
    ): array {
        $span = $monthsBefore + 1 + YearToDate::remainingMonths($asOf);

        ['tax' => $tax] = $this->annualTax($taxablePay * $span, $epfEmployee * $span, $profile);
        $zakat = (float) $profile->monthly_zakat * $span;

        return [
            'gross'  => $taxablePay * $months,
            'epf'    => $epfEmployee * $months,
            'pcb'    => round(max(0.0, $tax - $zakat) / $span * $months, 2),
            'zakat'  => 0.0,
            'months' => $months,
        ];
    }

    /**
     * HRD Corp levy — a percentage of the employee's wages, paid by the
     * employer on top of the payroll.
     *
     * Uncapped unless the company sets a ceiling: HRD Corp does not cap it the
     * way PERKESO caps SOCSO, but the setting exists for schemes that do.
     */
    private function hrdf(float $wages): float
    {
        $ceiling = $this->settings->hrdf_ceiling !== null ? (float) $this->settings->hrdf_ceiling : null;
        $base    = $ceiling !== null ? min($wages, $ceiling) : $wages;

        return round(max(0.0, $base) * (float) $this->settings->hrdf_employer_rate / 100, 2);
    }

    /**
     * The band a chargeable income falls in, as [start of band, rate].
     *
     * Returns the START of the band, not its ceiling — the MTD formula taxes
     * the slice above M at R and takes everything below it as B.
     *
     * @return array{0: float, 1: float}
     */
    private function bandFor(float $chargeable): array
    {
        $previous = 0.0;

        foreach ($this->settings->taxBands() as $band) {
            $upper = $band['up_to'] !== null ? (float) $band['up_to'] : null;

            if ($upper === null || $chargeable <= $upper) {
                return [$previous, (float) $band['rate'] / 100];
            }

            $previous = $upper;
        }

        // Past the last banded figure: the final band's rate applies from its
        // own start. Only reachable if the table has no open-ended top band.
        $last = collect($this->settings->taxBands())->last();

        return [$previous, (float) ($last['rate'] ?? 0) / 100];
    }

    /** Progressive tax on a chargeable income, using the company's bands. */
    public function taxOn(float $chargeable): float
    {
        $tax      = 0.0;
        $previous = 0.0;

        foreach ($this->settings->taxBands() as $band) {
            $upper = $band['up_to'] !== null ? (float) $band['up_to'] : null;
            $rate  = (float) $band['rate'];

            $slice = $upper === null
                ? max(0, $chargeable - $previous)
                : max(0, min($chargeable, $upper) - $previous);

            if ($slice > 0) {
                $tax += $slice * $rate / 100;
            }

            if ($upper === null || $chargeable <= $upper) {
                break;
            }

            $previous = $upper;
        }

        return round($tax, 2);
    }

    /** KWSP rounds a contribution up to the next whole ringgit. */
    private function ceilRinggit(float $amount): float
    {
        return (float) ceil(round($amount, 6));
    }
}
