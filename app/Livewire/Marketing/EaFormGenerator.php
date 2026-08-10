<?php

namespace App\Livewire\Marketing;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Borang EA (C.P.8A) for one employee, from figures somebody types in.
 *
 * Every Malaysian employer has to hand each employee a statement of the
 * previous year's remuneration by the end of February. A payroll system
 * produces it from its own committed runs — App\Services\Payroll\EaForm does
 * exactly that inside the product. A restaurant running payroll on a
 * spreadsheet has the same legal duty and no such button, which is who this
 * page is for: they have twelve payslips and need one form.
 *
 * TWO WAYS IN, BECAUSE PEOPLE HOLD THE DATA TWO WAYS.
 * Annual totals for anyone whose accountant already summed the year; month by
 * month for anyone working off a drawer of payslips, where the adding up is
 * itself the tedious part and the place errors get made. The monthly grid
 * feeds the totals, so the form is the same form either way.
 *
 * WHAT IT DELIBERATELY DOES NOT DO:
 *   - Recalculate anything. PCB, EPF, SOCSO and EIS are entered, not derived.
 *     A year's PCB is the sum of twelve monthly deductions actually made, and
 *     re-deriving it from an annual figure would produce a number that
 *     disagrees with the employee's own payslips and with what was remitted.
 *     The Salary Calculator is where the deriving happens, one month at a time.
 *   - Claim to be the official form. The PDF is a working copy in the EA's
 *     structure; LHDN's own C.P.8A is the filing document. The page and the
 *     PDF both say so, in those words — a tool that lets somebody believe they
 *     have filed when they have not is worse than no tool.
 */
class EaFormGenerator extends Component
{
    /** 'annual' | 'monthly' */
    public string $mode = 'annual';

    public int $year;

    // ---- Employer -----------------------------------------------------------
    public string $employerName = '';
    public string $employerRegNo = '';
    public string $employerTaxNo = '';
    public string $employerAddress = '';

    // ---- Employee -----------------------------------------------------------
    public string $employeeName = '';
    public string $icNumber = '';
    public string $staffId = '';
    public string $designation = '';
    public string $taxNumber = '';
    public string $epfNumber = '';
    public string $socsoNumber = '';
    public string $employedFrom = '';
    public string $employedTo = '';

    /**
     * The income lines, as the EA groups them.
     *
     * Service charge has its own line because in an F&B payroll it is often the
     * largest figure after salary, and it is employment income: leaving it out
     * understates what the employee has to declare.
     */
    public array $income = [
        'salary'         => 0,
        'allowances'     => 0,
        'overtime'       => 0,
        'service_charge' => 0,
        'bonus'          => 0,
        'director_fee'   => 0,
        'other'          => 0,
        'bik'            => 0,
        'accommodation'  => 0,
        'exempt'         => 0,
    ];

    public array $deductions = [
        'pcb'   => 0,
        'cp38'  => 0,
        'zakat' => 0,
        'tp1'   => 0,
    ];

    public array $contributions = [
        'epf_employee'   => 0,
        'epf_employer'   => 0,
        'socso_employee' => 0,
        'eis_employee'   => 0,
    ];

    /**
     * Twelve months of the columns people can read straight off a payslip.
     *
     * Not every EA line is here: a bonus or a benefit in kind is a once-a-year
     * figure, and asking for it twelve times to be entered once is how a form
     * gets abandoned. Those stay annual and are added on top.
     *
     * @var array<int, array<string, float>>
     */
    public array $months = [];

    /** Columns of the monthly grid, in order, with the total they feed. */
    public const MONTH_COLUMNS = [
        'salary'         => ['label' => 'Salary',   'feeds' => 'income'],
        'allowances'     => ['label' => 'Allow.',   'feeds' => 'income'],
        'overtime'       => ['label' => 'OT',       'feeds' => 'income'],
        'service_charge' => ['label' => 'Svc chg',  'feeds' => 'income'],
        'pcb'            => ['label' => 'PCB',      'feeds' => 'deductions'],
        'epf_employee'   => ['label' => 'EPF (e)',  'feeds' => 'contributions'],
        'epf_employer'   => ['label' => 'EPF (er)', 'feeds' => 'contributions'],
        'socso_employee' => ['label' => 'SOCSO',    'feeds' => 'contributions'],
        'eis_employee'   => ['label' => 'EIS',      'feeds' => 'contributions'],
    ];

    public string $downloadError = '';

    /** Downloads allowed per network in a day, as on the salary calculator. */
    private const PDFS_PER_DAY = 3;

    public function mount(): void
    {
        // An EA is issued for the year just finished, which is what somebody
        // sitting down to this in January or February wants.
        $this->year = (int) now()->subYear()->year;

        $this->months = collect(range(1, 12))
            ->map(fn () => array_fill_keys(array_keys(self::MONTH_COLUMNS), 0.0))
            ->all();

        $this->employedFrom = sprintf('%d-01-01', $this->year);
        $this->employedTo   = sprintf('%d-12-31', $this->year);
    }

    /**
     * Moving the year moves the default employment period with it, but only
     * while that period is still the untouched default — somebody who typed a
     * mid-year join date meant it.
     */
    public function updatedYear($value): void
    {
        $year = (int) $value;

        foreach ([['employedFrom', '-01-01'], ['employedTo', '-12-31']] as [$field, $suffix]) {
            if (preg_match('/^\d{4}' . preg_quote($suffix, '/') . '$/', $this->{$field})) {
                $this->{$field} = $year . $suffix;
            }
        }
    }

    /**
     * The figures the form is built from.
     *
     * In monthly mode the grid REPLACES the four income lines and every
     * statutory figure it covers; the once-a-year lines (bonus, benefits in
     * kind, zakat, CP38) are still taken from the annual fields and added, so
     * nothing entered is silently dropped.
     *
     * @return array<string, mixed>
     */
    public function figures(): array
    {
        $income        = array_map(fn ($v) => max(0.0, (float) $v), $this->income);
        $deductions    = array_map(fn ($v) => max(0.0, (float) $v), $this->deductions);
        $contributions = array_map(fn ($v) => max(0.0, (float) $v), $this->contributions);

        $monthsPaid = 0;

        if ($this->mode === 'monthly') {
            $totals = array_fill_keys(array_keys(self::MONTH_COLUMNS), 0.0);

            foreach ($this->months as $row) {
                $rowTotal = 0.0;

                foreach (array_keys(self::MONTH_COLUMNS) as $column) {
                    $value = max(0.0, (float) ($row[$column] ?? 0));
                    $totals[$column] += $value;
                    $rowTotal += $value;
                }

                if ($rowTotal > 0) {
                    $monthsPaid++;
                }
            }

            foreach (self::MONTH_COLUMNS as $column => $meta) {
                ${$meta['feeds']}[$column] = round($totals[$column], 2);
            }
        }

        // Part B: everything taxable. Exempt allowances are reported on the
        // form because the EA asks for them, but they are NOT part of the
        // gross — that is the entire point of their being exempt.
        $gross = $income['salary'] + $income['allowances'] + $income['overtime']
            + $income['service_charge'] + $income['bonus'] + $income['director_fee']
            + $income['other'] + $income['bik'] + $income['accommodation'];

        $totalDeductions = $deductions['pcb'] + $deductions['cp38'] + $deductions['zakat'];

        return [
            'ready'         => $gross > 0,
            'income'        => array_map(fn ($v) => round($v, 2), $income),
            'deductions'    => array_map(fn ($v) => round($v, 2), $deductions),
            'contributions' => array_map(fn ($v) => round($v, 2), $contributions),
            'gross'         => round($gross, 2),
            'total_deducted' => round($totalDeductions, 2),
            'months_paid'   => $monthsPaid,
            // What the employee is left holding, which is not an EA box but is
            // the sanity check that catches a mistyped column.
            'net'           => round($gross - $totalDeductions - $contributions['epf_employee']
                - $contributions['socso_employee'] - $contributions['eis_employee'], 2),
        ];
    }

    public function downloadPdf()
    {
        $this->downloadError = '';

        $figures = $this->figures();

        if (! $figures['ready']) {
            $this->downloadError = 'Enter the year\'s pay first.';

            return null;
        }

        if (trim($this->employeeName) === '') {
            $this->downloadError = 'An EA form needs the employee\'s name on it.';

            return null;
        }

        $key = 'ea-form-pdf:ip:' . (request()->ip() ?: 'unknown');

        if (RateLimiter::tooManyAttempts($key, self::PDFS_PER_DAY)) {
            $this->downloadError = 'That is ' . self::PDFS_PER_DAY . ' downloads today. '
                . 'Try again tomorrow, or start a free trial to issue EA forms for the whole team at once.';

            return null;
        }

        // A day in seconds, written out: now()->addDay()->diffInSeconds(now())
        // is NEGATIVE, which silently makes the limit no limit at all.
        RateLimiter::hit($key, 86400);

        $pdf = Pdf::loadView('pdf.marketing.ea-form', [
            'figures'  => $figures,
            'year'     => $this->year,
            'employer' => [
                'name'    => trim($this->employerName),
                'reg_no'  => trim($this->employerRegNo),
                'tax_no'  => trim($this->employerTaxNo),
                'address' => trim($this->employerAddress),
            ],
            'employee' => [
                'name'        => trim($this->employeeName),
                'ic_number'   => trim($this->icNumber),
                'staff_id'    => trim($this->staffId),
                'designation' => trim($this->designation),
                'tax_number'  => trim($this->taxNumber),
                'epf_number'  => trim($this->epfNumber),
                'socso_number' => trim($this->socsoNumber),
                'from'        => $this->employedFrom,
                'to'          => $this->employedTo,
            ],
            'months'      => $this->mode === 'monthly' ? $this->months : [],
            'monthLabels' => self::MONTH_COLUMNS,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'ea-form-' . $this->year . '-' . Str::slug($this->employeeName) . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function render()
    {
        return view('livewire.marketing.ea-form-generator', [
            'figures'      => $this->figures(),
            'monthColumns' => self::MONTH_COLUMNS,
        ])->layout('layouts.marketing', [
            'title' => 'Free Borang EA Generator — EA Form for Malaysian Employers',
        ]);
    }
}
