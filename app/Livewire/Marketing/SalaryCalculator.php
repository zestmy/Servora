<?php

namespace App\Livewire\Marketing;

use App\Models\StatutorySetting;
use Livewire\Component;

/**
 * Monthly take-home after EPF, SOCSO, EIS and PCB.
 *
 * THE RATES COME FROM THE PAYROLL ENGINE'S OWN DEFAULTS —
 * StatutorySetting::defaults() and ::DEFAULT_TAX_BANDS, the same numbers a
 * customer's payroll run starts from. A second copy on a public page is a
 * second thing to update when KWSP or LHDN move, and the copy nobody remembers
 * is the one strangers are reading.
 *
 * THREE DIFFERENT WAGES, WHICH IS THE WHOLE POINT ONCE ALLOWANCES EXIST.
 * Malaysian statutory law does not have a single idea of "pay":
 *
 *   EPF wages    basic + fixed allowances. EXCLUDES overtime and service
 *                charge — both are named exclusions in the EPF Act's
 *                definition of wages, and both get added by mistake often.
 *   SOCSO / EIS  basic + fixed allowances + overtime. EXCLUDES service charge.
 *   Taxable      all of that PLUS service charge, which is employment income
 *                even though it is not "wages" for any of the three funds.
 *
 * So a service charge raises the tax and nothing else, and a heavy overtime
 * month moves SOCSO but never EPF. This mirrors the internal
 * StatutoryCalculator, which takes those three bases as separate arguments for
 * exactly this reason.
 *
 * PCB HERE IS AN ESTIMATE AND SAYS SO ON THE PAGE.
 */
class SalaryCalculator extends Component
{
    /**
     * How each added line is treated.
     *
     * The three flags mirror PayComponent's own — is_taxable, epf_applicable,
     * socso_applicable — rather than inventing a second vocabulary for the same
     * three questions. Offered as three named cases rather than three
     * checkboxes because "fixed or variable?" is a question an operator can
     * answer and "is this SOCSO-applicable?" is not.
     */
    public const ALLOWANCE_TYPES = [
        'fixed' => [
            'label' => 'Fixed allowance',
            'note'  => 'Paid every month as part of the package — housing, transport, position.',
            'epf' => true, 'socso' => true, 'taxable' => true,
        ],
        'variable' => [
            'label' => 'Variable allowance',
            'note'  => 'Changes month to month. Taxable, but outside the statutory wage base.',
            'epf' => false, 'socso' => false, 'taxable' => true,
        ],
        'reimbursement' => [
            'label' => 'Reimbursement',
            'note'  => 'Money back for something already spent. Not wages and not taxable.',
            'epf' => false, 'socso' => false, 'taxable' => false,
        ],
    ];

    public float $basic = 0;

    /** @var array<int, array{label: string, amount: float, type: string}> */
    public array $allowances = [];

    /**
     * Overtime rates under the Employment Act 1955.
     *
     * The multipliers are the statutory minimums for hours worked BEYOND normal
     * hours. A rest day or holiday also carries its own day's pay for the normal
     * hours themselves, which is a separate entitlement and not overtime — this
     * tool prices the overtime, and the page says so rather than implying it has
     * priced the whole day.
     */
    public const OT_TYPES = [
        'normal' => [
            'label' => 'Normal working day',
            'rate'  => 1.5,
            'note'  => 'Hours beyond the normal working day.',
        ],
        'rest_day' => [
            'label' => 'Rest day',
            'rate'  => 2.0,
            'note'  => 'Hours beyond normal hours on a rest day.',
        ],
        'public_holiday' => [
            'label' => 'Public holiday',
            'rate'  => 3.0,
            'note'  => 'Hours beyond normal hours on a public holiday.',
        ],
    ];

    /** Days a month used to derive the ordinary rate of pay, per the Act. */
    private const DAYS_PER_MONTH = 26;

    /** @var array<string, float> hours worked, by overtime type */
    public array $otHours = ['normal' => 0, 'rest_day' => 0, 'public_holiday' => 0];

    public float $normalHoursPerDay = 8;

    /**
     * Overtime paid outside the hours above — a fixed OT allowance, or a figure
     * somebody already has off a payslip and does not want to re-derive.
     *
     * Overtime of either kind is SOCSO and EIS wages but NEVER EPF: a named
     * exclusion in the EPF Act, and one of the commonest payroll mistakes in the
     * trade, quietly overstating EPF for everyone who worked a busy month.
     */
    public float $overtime = 0;

    /**
     * Service charge: taxable, but not wages for any of the three funds.
     *
     * In an F&B payroll this is often the largest line after basic, so treating
     * it as wages is not a rounding error.
     */
    public float $serviceCharge = 0;

    public bool $isSenior = false;

    public bool $isMalaysian = true;

    /** 'single' | 'spouse_not_working' | 'spouse_working' */
    public string $category = 'single';

    public int $children = 0;

    public function addAllowance(): void
    {
        $this->allowances[] = ['label' => '', 'amount' => 0, 'type' => 'fixed'];
    }

    public function removeAllowance(int $index): void
    {
        unset($this->allowances[$index]);
        $this->allowances = array_values($this->allowances);
    }

    /** @return array<string, mixed> */
    public function figures(): array
    {
        $d     = StatutorySetting::defaults();
        $basic = max(0.0, (float) $this->basic);
        $svc   = max(0.0, (float) $this->serviceCharge);

        $allowances = ['epf' => 0.0, 'socso' => 0.0, 'taxable' => 0.0, 'total' => 0.0];

        foreach ($this->allowances as $line) {
            $amount = max(0.0, (float) ($line['amount'] ?? 0));
            $rules  = self::ALLOWANCE_TYPES[$line['type'] ?? 'fixed'] ?? self::ALLOWANCE_TYPES['fixed'];

            $allowances['total'] += $amount;

            foreach (['epf', 'socso', 'taxable'] as $basis) {
                if ($rules[$basis]) {
                    $allowances[$basis] += $amount;
                }
            }
        }

        /*
         * The ordinary rate of pay, per the Employment Act: monthly wages over
         * 26 days, then over the normal hours in a day.
         *
         * Built on basic PLUS FIXED ALLOWANCES, because those are the wages the
         * employee is entitled to for their normal hours — the same base EPF
         * uses. Basing it on bare basic understates every overtime hour for
         * anybody on an allowance, which is most of a kitchen.
         */
        $otWages    = $basic + $allowances['epf'];
        $hoursInDay = max(1.0, (float) $this->normalHoursPerDay);
        $hourlyRate = $otWages > 0 ? $otWages / self::DAYS_PER_MONTH / $hoursInDay : 0.0;

        $otLines = [];
        $otFromHours = 0.0;

        foreach (self::OT_TYPES as $key => $type) {
            $hours = max(0.0, (float) ($this->otHours[$key] ?? 0));

            if ($hours <= 0) {
                continue;
            }

            $pay = $hours * $type['rate'] * $hourlyRate;
            $otFromHours += $pay;

            $otLines[] = [
                'label' => $type['label'],
                'rate'  => $type['rate'],
                'hours' => $hours,
                'pay'   => round($pay, 2),
            ];
        }

        $ot = $otFromHours + max(0.0, (float) $this->overtime);

        $gross = $basic + $allowances['total'] + $ot + $svc;

        if ($gross <= 0) {
            return ['ready' => false];
        }

        // Each base excludes exactly what the law says it excludes.
        $epfWage   = $basic + $allowances['epf'];
        $socsoWage = $basic + $allowances['socso'] + $ot;
        $taxable   = $basic + $allowances['taxable'] + $ot + $svc;

        // ---- EPF -------------------------------------------------------------
        [$epfEmpRate, $epfErRate] = match (true) {
            ! $this->isMalaysian => [$d['epf_employee_rate_foreign'], $d['epf_employer_rate_foreign']],
            $this->isSenior      => [$d['epf_employee_rate_senior'], $d['epf_employer_rate_senior']],
            default              => [
                $d['epf_employee_rate'],
                $epfWage > $d['epf_wage_threshold'] ? $d['epf_employer_rate_high'] : $d['epf_employer_rate_low'],
            ],
        };

        // KWSP rounds a contribution up to the next whole ringgit, which is why
        // a payslip never shows cents against EPF.
        $epfEmployee = ceil($epfWage * $epfEmpRate / 100);
        $epfEmployer = ceil($epfWage * $epfErRate / 100);

        // ---- SOCSO and EIS ---------------------------------------------------
        $socsoCapped = min($socsoWage, (float) $d['socso_ceiling']);
        $eisCapped   = min($socsoWage, (float) $d['eis_ceiling']);

        $socsoEmployee = $this->isSenior ? 0.0 : round($socsoCapped * $d['socso_employee_rate'] / 100, 2);
        $socsoEmployer = round($socsoCapped * ($this->isSenior ? $d['socso_employer_rate_senior'] : $d['socso_employer_rate']) / 100, 2);

        $eisEmployee = $this->isSenior ? 0.0 : round($eisCapped * $d['eis_employee_rate'] / 100, 2);
        $eisEmployer = $this->isSenior ? 0.0 : round($eisCapped * $d['eis_employer_rate'] / 100, 2);

        // ---- PCB estimate ----------------------------------------------------
        $annual  = $taxable * 12;
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
            'gross'          => round($gross, 2),
            'basic'          => round($basic, 2),
            'allowances'     => round($allowances['total'], 2),
            'overtime'       => round($ot, 2),
            'ot_lines'       => $otLines,
            'hourly_rate'    => round($hourlyRate, 2),
            'ot_wages'       => round($otWages, 2),
            'service_charge' => round($svc, 2),
            'epf_wage'       => round($epfWage, 2),
            'socso_wage'     => round($socsoWage, 2),
            'taxable_wage'   => round($taxable, 2),
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
            'net'            => round($gross - $deductions, 2),
            // What the person costs, which an owner budgets on and a salary
            // negotiation usually forgets.
            'employer_cost'  => round($gross + $epfEmployer + $socsoEmployer + $eisEmployer, 2),
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
            'figures'        => $this->figures(),
            'categories'     => StatutorySetting::PCB_CATEGORIES,
            'allowanceTypes' => self::ALLOWANCE_TYPES,
            'otTypes'        => self::OT_TYPES,
        ])->layout('layouts.marketing', [
            'title' => 'Free Salary Calculator — EPF, SOCSO, EIS & PCB',
        ]);
    }
}
