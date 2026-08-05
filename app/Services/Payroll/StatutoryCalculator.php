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
 *  2. PCB here is the ANNUALISED ESTIMATE, not the full MTD formula from the
 *     Income Tax (Deduction from Remuneration) Rules. The real formula carries
 *     year-to-date remuneration and year-to-date MTD forward, which needs a
 *     payroll run history this system does not keep yet. For steady monthly pay
 *     the two agree closely; for a month with a bonus or a mid-year joiner they
 *     will not. Treat it as an indication and check LHDN's calculator.
 *
 * Every figure this returns is labelled as an estimate in the UI for exactly
 * these reasons.
 */
class StatutoryCalculator
{
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
    ): array {
        $asOf    = $asOf ?? Carbon::today();
        $profile = $profile ?? EmployeeStatutoryProfile::forEmployee($employee);
        $age     = $employee->date_of_birth?->diffInYears($asOf);

        $notes  = [];
        $result = [
            'epf_employee' => 0.0, 'epf_employer' => 0.0,
            'socso_employee' => 0.0, 'socso_employer' => 0.0,
            'eis_employee' => 0.0, 'eis_employer' => 0.0,
            'pcb' => 0.0,
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
            $result['pcb'] = $this->pcb($taxablePay, $result['epf_employee'], $profile);
            $notes[] = 'PCB is an annualised estimate, not the LHDN MTD formula.';
        }

        $result['employee_total'] = round(
            $result['epf_employee'] + $result['socso_employee'] + $result['eis_employee'] + $result['pcb'], 2
        );
        $result['employer_total'] = round(
            $result['epf_employer'] + $result['socso_employer'] + $result['eis_employer'], 2
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
     * PCB, annualised.
     *
     * Take the month's taxable pay times twelve, subtract the annual reliefs
     * the profile allows for, run it through the resident tax bands, divide by
     * twelve, then take off monthly zakat. Never negative.
     *
     * This is NOT the statutory MTD formula — see the class docblock. It is
     * deliberately conservative in shape (it does not carry year-to-date
     * figures) and is presented as an estimate everywhere it is shown.
     */
    private function pcb(float $taxablePay, float $epfEmployee, EmployeeStatutoryProfile $profile): float
    {
        if ($taxablePay <= 0) {
            return 0.0;
        }

        $annualIncome = $taxablePay * 12;

        // EPF relief is capped annually, so twelve months of contribution can
        // only ever relieve up to the cap.
        $epfRelief = min($epfEmployee * 12, (float) $this->settings->pcb_relief_epf_cap);

        $relief = (float) $this->settings->pcb_relief_individual
            + $epfRelief
            + ($profile->pcb_category === 'spouse_not_working' ? (float) $this->settings->pcb_relief_spouse : 0)
            + ($profile->children * (float) $this->settings->pcb_relief_child)
            + (float) $profile->annual_other_relief;

        $chargeable = max(0, $annualIncome - $relief);
        $annualTax  = $this->taxOn($chargeable);

        // The individual rebate for a small chargeable income. Leaving it out
        // deducts tax from low-paid staff who owe none — the most visible way
        // this calculation can be wrong. A second rebate applies where the
        // spouse has no income.
        if ($chargeable > 0 && $chargeable <= (float) $this->settings->pcb_rebate_threshold) {
            $rebate = (float) $this->settings->pcb_rebate_amount;
            if ($profile->pcb_category === 'spouse_not_working') {
                $rebate *= 2;
            }
            $annualTax = max(0, $annualTax - $rebate);
        }

        $monthly = $annualTax / 12 - (float) $profile->monthly_zakat;

        return round(max(0, $monthly), 2);
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
