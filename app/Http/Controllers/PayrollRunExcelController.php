<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The payroll run as a spreadsheet — the working copy of the figures.
 *
 * The same document as the run list PDF and available on the same terms: on a
 * DRAFT as well as an approved run, unlike the statutory and bank exports.
 * Those commit the company to a figure and are rightly held back until
 * approval; this exists so the numbers can be worked on BEFORE that decision,
 * which is when checking them is still useful. It carries more columns than
 * the PDF because a sheet has no page width to run out of, and the point of a
 * spreadsheet here is to sort, filter and cross-total against something else.
 *
 * WHAT IT IS NOT: a payment instruction. A draft can still be regenerated, so
 * a file taken off one is a snapshot of figures that may move. The sheet says
 * so on its own face — a banner above the headings and DRAFT in the filename —
 * because a caveat that lives only on the screen the file came from is lost
 * the moment it is emailed to somebody else. To actually pay anybody, use the
 * salary payment file from an approved run.
 */
class PayrollRunExcelController extends Controller
{
    /**
     * Every column, in order: heading, kind, and how to read it off a line.
     *
     * ONE list rather than a header list beside a value list. Those two drift:
     * a value pushed where a heading was not shifts every column after it, and
     * the result reads as wrong figures rather than as a missing column.
     *
     * The KIND decides both the number format and whether the value is written
     * explicitly. An IC number and a bank account are digits that are NOT
     * quantities — written as numbers they lose their leading zeros and, past
     * fifteen digits, their last digits to floating point.
     *
     * @return array<int, array{0: string, 1: string, 2: callable}>
     */
    private function columns(bool $hasService, bool $hasAdjust, bool $hasEmploymentChange): array
    {
        $money = fn (string $attr) => fn (PayrollRunLine $l) => (float) $l->{$attr};

        return array_values(array_filter([
            ['Employee',         'text',  fn ($l) => $l->employee_name],
            ['Staff ID',         'text',  fn ($l) => $l->staff_id],
            ['IC No.',           'text',  fn ($l) => $l->ic_number],
            ['Designation',      'text',  fn ($l) => $l->designation],
            ['Outlet',           'text',  fn ($l) => $l->outlet_name],
            ['Section',          'text',  fn ($l) => $l->section_name],
            ['Pay Type',         'text',  fn ($l) => Employee::PAY_TYPES[$l->pay_type] ?? $l->pay_type],
            // The working behind basic, in the words the payslip uses: hours
            // for hourly staff, days for daily, "12 of 31 days" for a monthly
            // employee on a part month.
            ['Basis',            'text',  fn ($l) => $this->basisLabel($l)],
            // WHY it is short, beside the Basis that says by how much. Left
            // out entirely when nobody on the run joined or left inside it,
            // which is the ordinary case.
            $hasEmploymentChange
                ? ['Employment', 'text', fn ($l) => $l->employmentNote()]
                : null,
            ['Basic',            'money', $money('basic')],
            ['Allowances',       'money', $money('allowances')],
            ['OT Hours',         'hours', $money('ot_hours')],
            ['OT Amount',        'money', $money('ot_amount')],
            $hasService ? ['Service Charge', 'money', $money('service_charge')] : null,
            $hasAdjust  ? ['Adjustments',    'money', $money('adjustments_total')] : null,
            ['Gross',            'money', $money('gross')],
            ['EPF (Emp)',        'money', $money('epf_employee')],
            ['SOCSO (Emp)',      'money', $money('socso_employee')],
            ['EIS (Emp)',        'money', $money('eis_employee')],
            ['PCB',              'money', $money('pcb')],
            ['Zakat',            'money', $money('zakat')],
            ['SKBBK',            'money', $money('skbbk')],
            ['Statutory (Emp)',  'money', $money('statutory_employee')],
            ['Other Deductions', 'money', $money('deductions')],
            ['Net Pay',          'money', $money('net')],
            ['EPF (Er)',         'money', $money('epf_employer')],
            ['SOCSO (Er)',       'money', $money('socso_employer')],
            ['EIS (Er)',         'money', $money('eis_employer')],
            ['HRDF',             'money', $money('hrdf_employer')],
            ['Statutory (Er)',   'money', $money('statutory_employer')],
            ['Employer Cost',    'money', $money('employer_cost')],
            ['Bank',             'text',  fn ($l) => $l->bank_name],
            ['Account No.',      'text',  fn ($l) => $l->bank_account_no],
            // Only ever populated when the account is genuinely somebody
            // else's — see PayrollRunBuilder, which stores it that way.
            ['Account Holder',   'text',  fn ($l) => $l->bank_account_name],
        ]));
    }

    public function __invoke(string $run)
    {
        abort_unless(Auth::user()?->can('hr.payroll'), 403);

        $payrollRun = PayrollRun::with('outlet:id,name', 'section:id,name', 'approvedBy:id,name')
            ->where('uuid', $run)
            ->firstOrFail();

        // The global scope already limits this to the viewer's company; the
        // outlet check is what stops a run for a branch they cannot see.
        abort_unless(
            $payrollRun->outlet_id === null
                || in_array((int) $payrollRun->outlet_id, Auth::user()->accessibleOutletIds(), true),
            403,
        );

        $lines = $payrollRun->lines()->orderBy('employee_name')->get();

        // A column that would be a stripe of zeroes on most runs is left out
        // rather than shown empty — the same call the list PDF makes about
        // service charge, for the same reason.
        $hasService = $lines->sum(fn ($l) => (float) $l->service_charge) > 0;
        $hasAdjust  = $lines->contains(fn ($l) => (float) $l->adjustments_total != 0.0);
        $hasEmploymentChange = $lines->contains(fn ($l) => $l->employmentNote() !== null);

        $columns = $this->columns($hasService, $hasAdjust, $hasEmploymentChange);

        $company   = Company::find($payrollRun->company_id);
        $brandName = $company?->brand_name ?: $company?->name;

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(Auth::user()->name)
            ->setTitle('Payroll ' . $payrollRun->reference);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll');

        // Column 1 is the row number, so the declared columns start at 2.
        $firstDataCol = 2;
        $lastCol = Coordinate::stringFromColumnIndex(count($columns) + 1);

        // ── Title block ──────────────────────────────────────────────────
        $sheet->setCellValueExplicit(
            'A1',
            $brandName . ' — Payroll ' . $payrollRun->reference,
            DataType::TYPE_STRING
        );
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEF2FF');
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->setCellValueExplicit('A2', implode(' · ', array_filter([
            $payrollRun->periodLabel(),
            // Spelled out only when the range is not the month it is named
            // after — with a 26th–25th cycle, "August" is not August.
            $payrollRun->hasCustomRange() ? $payrollRun->rangeLabel() : null,
            $payrollRun->scopeLabel(),
            $payrollRun->statusLabel(),
            $lines->count() . ' employee(s)',
            'Generated ' . now()->format('d M Y, h:i A') . ' by ' . Auth::user()->name,
        ])), DataType::TYPE_STRING);
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setARGB('FF6B7280');

        /*
         * THE DRAFT BANNER, in the file rather than only on the screen.
         *
         * A draft can be regenerated, so these figures are not yet what
         * anybody is owed. It sits above the headings where it cannot be
         * scrolled past, and it is omitted entirely once the run is approved
         * rather than reworded — a banner that is always there is one nobody
         * reads.
         */
        $headerRow = 4;

        if (! $payrollRun->isApproved()) {
            $sheet->setCellValueExplicit(
                'A3',
                'DRAFT — not approved. These figures can still change if the run is regenerated. '
                    . 'Do not pay from this sheet; use the salary payment file from the approved run.',
                DataType::TYPE_STRING
            );
            $sheet->mergeCells('A3:' . $lastCol . '3');
            $sheet->getStyle('A3')->getFont()->setBold(true)->getColor()->setARGB('FF92400E');
            $sheet->getStyle('A3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF3C7');
            $sheet->getRowDimension(3)->setRowHeight(18);
        }

        // ── Header row ───────────────────────────────────────────────────
        $sheet->setCellValueExplicit('A' . $headerRow, 'No.', DataType::TYPE_STRING);

        foreach ($columns as $i => [$label, $kind, $read]) {
            $sheet->setCellValueExplicit([$i + $firstDataCol, $headerRow], $label, DataType::TYPE_STRING);
        }

        $headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937');
        $sheet->getStyle($headerRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        // ── Data rows ────────────────────────────────────────────────────
        $row = $headerRow;

        foreach ($lines as $i => $line) {
            $row++;

            $sheet->setCellValue('A' . $row, $i + 1);

            foreach ($columns as $c => [$label, $kind, $read]) {
                $value = $read($line);

                if ($kind === 'text') {
                    // Explicit strings, so an account number keeps its leading
                    // zeros and a name beginning "=" is not read as a formula.
                    $sheet->setCellValueExplicit(
                        [$c + $firstDataCol, $row],
                        (string) ($value ?? ''),
                        DataType::TYPE_STRING
                    );
                } else {
                    $sheet->setCellValue([$c + $firstDataCol, $row], (float) $value);
                }
            }

            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF9FAFB');
            }
        }

        $lastDataRow = $row;

        // ── Totals ───────────────────────────────────────────────────────
        if ($lines->isNotEmpty()) {
            $row++;
            $sheet->setCellValueExplicit('A' . $row, 'TOTAL', DataType::TYPE_STRING);

            foreach ($columns as $c => [$label, $kind, $read]) {
                if (! in_array($kind, ['money', 'hours'], true)) {
                    continue;
                }

                $col = Coordinate::stringFromColumnIndex($c + $firstDataCol);

                /*
                 * SUBTOTAL(9,…) rather than SUM, and a formula rather than a
                 * figure computed here.
                 *
                 * Whoever opens this will filter it — by outlet, by section,
                 * by who is still unpaid — and SUBTOTAL re-totals what the
                 * filter left. A pasted constant would sit under a filtered
                 * column stating the total of rows no longer on screen, which
                 * is the kind of wrong that gets copied into a report.
                 */
                $sheet->setCellValue(
                    $col . $row,
                    '=SUBTOTAL(9,' . $col . ($headerRow + 1) . ':' . $col . $lastDataRow . ')'
                );
            }

            $totalRange = 'A' . $row . ':' . $lastCol . $row;
            $sheet->getStyle($totalRange)->getFont()->setBold(true);
            $sheet->getStyle($totalRange)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEF2FF');
            $sheet->getStyle($totalRange)->getBorders()->getTop()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF9CA3AF');
        }

        // ── Formats and furniture ────────────────────────────────────────
        if ($lastDataRow > $headerRow) {
            $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $row)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE5E7EB');

            // The filter covers the data only — a totals row inside the range
            // gets sorted in among the employees.
            $sheet->setAutoFilter('A' . $headerRow . ':' . $lastCol . $lastDataRow);

            foreach ($columns as $c => [$label, $kind, $read]) {
                if ($kind === 'text') {
                    continue;
                }

                $col = Coordinate::stringFromColumnIndex($c + $firstDataCol);
                $sheet->getStyle($col . ($headerRow + 1) . ':' . $col . $row)
                    ->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }

        // Frozen below the headings and to the right of the name, so scrolling
        // out to the employer-cost columns keeps saying whose row it is.
        $sheet->freezePane('C' . ($headerRow + 1));

        foreach (range(1, count($columns) + 1) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        // The status is in the filename as well as the banner: a draft and an
        // approved export of the same run must not land in somebody's
        // downloads folder under the same name.
        $filename = 'payroll-' . $payrollRun->reference
            . ($payrollRun->isApproved() ? '' : '-DRAFT') . '.xlsx';

        $tmp = tempnam(sys_get_temp_dir(), 'payxlsx');
        (new Xlsx($spreadsheet))->save($tmp);

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** The working behind basic, in the words the payslip uses. */
    private function basisLabel(PayrollRunLine $line): ?string
    {
        return match (true) {
            $line->isHourly() => number_format((float) $line->paid_hours, 2)
                . ' h × ' . number_format((float) $line->pay_rate, 2),
            $line->isDaily() => (int) $line->paid_days
                . ' d × ' . number_format((float) $line->pay_rate, 2),
            $line->isProrated() => $line->prorationLabel(),
            default => null,
        };
    }
}
