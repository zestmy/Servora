<?php

namespace App\Livewire\Marketing;

use App\Models\StatutorySetting;
use Livewire\Component;

/**
 * Monthly take-home after EPF, SOCSO, EIS and PCB.
 *
 * THE NUMBERS COME FROM THE PAYROLL ENGINE'S OWN DEFAULTS —
 * StatutorySetting::defaults() and ::DEFAULT_TAX_BANDS, the same constants a
 * customer's payroll run starts from. A second copy of the rates on a public
 * page is a second thing to update when KWSP or LHDN move, and the copy nobody
 * remembers is the one strangers are reading.
 *
 * PCB HERE IS AN ESTIMATE AND SAYS SO. The real MTD schedule takes the year to
 * date, every relief actually claimed, zakat, rebates and prior deductions.
 * This applies the standard reliefs to an annualised salary and divides by
 * twelve, which is the right shape and the wrong precision — close enough to
 * plan with, not close enough to file. The page states that rather than
 * implying a payslip.
 */
class SalaryCalculator extends Component
{
    public float $salary = 0;

    /** Under 60 and over 60 are different rates for all three schemes. */
    public bool $isSenior = false;

    public bool $isMalaysian = true;

    /** 'single' | 'spouse_not_working' | 'spouse_working' */
    public string $category = 'single';

    public int $children = 0;

    /** @return array<string, mixed> */
    public function figures(): array
    {
        $d     = StatutorySetting::defaults();
        $wage  = max(0.0, (float) $this->salary);

        if ($wage <= 0) {
            return ['ready' => false];
        }

        // ---- EPF ---------------------------------------------------------
        // A non-citizen contributes at the foreign rate; over-60s at the senior
        // rate; and the employer's share steps down above the wage threshold.
        [$epfEmpRate, $epfErRate] = match (true) {
            ! $this->isMalaysian => [$d['epf_employee_rate_foreign'], $d['epf_employer_rate_foreign']],
            $this->isSenior      => [$d['epf_employee_rate_senior'], $d['epf_employer_rate_senior']],
            default              => [
                $d['epf_employee_rate'],
                $wage > $d['epf_wage_threshold'] ? $d['epf_employer_rate_high'] : $d['epf_employer_rate_low'],
            ],
        };

        // KWSP rounds a contribution up to the next whole ringgit, which is why
        // a payslip never shows cents against EPF.
        $epfEmployee = ceil($wage * $epfEmpRate / 100);
        $epfEmployer = ceil($wage * $epfErRate / 100);

        // ---- SOCSO and EIS ------------------------------------------------
        // Both are capped, and both stop or change at 60.
        $socsoWage = min($wage, (float) $d['socso_ceiling']);
        $eisWage   = min($wage, (float) $d['eis_ceiling']);

        $socsoEmployee = $this->isSenior ? 0.0 : round($socsoWage * $d['socso_employee_rate'] / 100, 2);
        $socsoEmployer = round($socsoWage * ($this->isSenior ? $d['socso_employer_rate_senior'] : $d['socso_employer_rate']) / 100, 2);

        // EIS does not apply from the age ceiling upward.
        $eisEmployee = $this->isSenior ? 0.0 : round($eisWage * $d['eis_employee_rate'] / 100, 2);
        $eisEmployer = $this->isSenior ? 0.0 : round($eisWage * $d['eis_employer_rate'] / 100, 2);

        // ---- PCB estimate --------------------------------------------------
        $annual  = $wage * 12;
        $reliefs = (float) $d['pcb_relief_individual']
            + min($epfEmployee * 12, (float) $d['pcb_relief_epf_cap'])
            + ($this->category === 'spouse_not_working' ? (float) $d['pcb_relief_spouse'] : 0)
            + max(0, $this->children) * (float) $d['pcb_relief_child'];

        $chargeable = max(0.0, $annual - $reliefs);
        $annualTax  = max(0.0, $this->taxOn($chargeable) - (float) $d['pcb_rebate_amount']);
        $pcb        = round($annualTax / 12, 2);

        $deductions = $epfEmployee + $socsoEmployee + $eisEmployee + $pcb;

        return [
            'ready'          => true,
            'gross'          => round($wage, 2),
            'epf_employee'   => $epfEmployee,
            'epf_employer'   => $epfEmployer,
            'epf_rate'       => $epfEmpRate,
            'socso_employee' => $socsoEmployee,
            'socso_employer' => $socsoEmployer,
            'eis_employee'   => $eisEmployee,
            'eis_employer'   => $eisEmployer,
            'pcb'            => $pcb,
            'chargeable'     => round($chargeable, 2),
            'deductions'     => round($deductions, 2),
            'net'            => round($wage - $deductions, 2),
            // What the employee costs, which is the number an owner budgets on
            // and the one a salary negotiation usually forgets.
            'employer_cost'  => round($wage + $epfEmployer + $socsoEmployer + $eisEmployer, 2),
        ];
    }

    /** Progressive tax across the resident bands. */
    private function taxOn(float $chargeable): float
    {
        $tax   = 0.0;
        $lower = 0.0;

        foreach (StatutorySetting::DEFAULT_TAX_BANDS as $band) {
            $upper = $band['up_to'] ?? INF;

            if ($chargeable <= $lower) {
                break;
            }

            $tax  += (min($chargeable, $upper) - $lower) * ($band['rate'] / 100);
            $lower = $upper;
        }

        return round($tax, 2);
    }

    public function render()
    {
        return view('livewire.marketing.salary-calculator', [
            'figures'    => $this->figures(),
            'categories' => StatutorySetting::PCB_CATEGORIES,
        ])->layout('layouts.marketing', [
            'title' => 'Free Salary Calculator — EPF, SOCSO, EIS & PCB',
        ]);
    }
}
