<?php

namespace App\Http\Controllers;

use App\Models\LabourCostTransferLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * One labour cost transfer as a workbook — the same document as its PDF,
 * built from the same load() (this extends the PDF controller), so the same
 * hr.compensation gate and either-end outlet rule apply.
 *
 * One sheet: the transfer's details, its employee lines (total = salary + OT,
 * live) and the summary by outlet (net = received − salary out − OT out,
 * live, summing to zero).
 */
class LabourCostTransferExcelController extends LabourCostTransferPdfController
{
    private const MONEY = '#,##0.00';
    private const QTY   = '0.00';
    private const DATE  = 'dd mmm yyyy';
    private const NET   = '+#,##0.00;−#,##0.00;0.00';

    private const HEADER_FILL  = 'FFE5E7EB';
    private const TOTAL_FILL   = 'FFE5E7EB';

    public function __invoke(Request $request, int $id)
    {
        ['transfer' => $t, 'summary' => $summary, 'outletNames' => $names, 'company' => $company] = $this->load($request, $id);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(Auth::user()->name)
            ->setTitle('Labour Cost Transfer ' . $t->transfer_number);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Labour Cost Transfer');

        $widths = ['A' => 5, 'B' => 28, 'C' => 11, 'D' => 18, 'E' => 12, 'F' => 12, 'G' => 12,
                   'H' => 8, 'I' => 8, 'J' => 11, 'K' => 12, 'L' => 8, 'M' => 11, 'N' => 13, 'O' => 13];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $row = $this->writeDetails($sheet, $t, $company?->name);
        $row = $this->writeLines($sheet, $t, $row);
        $this->writeSummary($sheet, $summary, $names, $row + 1);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, 'Labour-Cost-Transfer-' . $t->transfer_number . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function band(Worksheet $sheet, string $range, string $argb): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
    }

    /** @return int the next free row */
    private function writeDetails(Worksheet $sheet, $t, ?string $companyName): int
    {
        $sheet->setCellValue('A1', 'LABOUR COST TRANSFER');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $meta = [
            ['Company', $companyName ?? '—'],
            ['Transfer #', $t->transfer_number],
            ['Status', $t->statusLabel()],
            ['Date', 'date'],
            ['Charged to', $t->toOutlet?->name ?? '—'],
            ['Purpose', $t->purposeLabel()],
        ];
        if ($t->reference) $meta[] = ['Event / reference', $t->reference];
        $meta[] = ['Prepared by', trim(($t->createdBy?->name ?? '—') . ' ' . ($t->created_at?->format('d M Y H:i') ?? ''))];
        if ($t->confirmedBy) $meta[] = ['Confirmed by', $t->confirmedBy->name . ' ' . $t->confirmed_at?->format('d M Y H:i')];
        if ($t->notes) $meta[] = ['Notes', $t->notes];

        $row = 2;
        foreach ($meta as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);

            if ($value === 'date') {
                $sheet->setCellValue("C{$row}", ExcelDate::PHPToExcel($t->transfer_date));
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::DATE);
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            } else {
                $sheet->setCellValueExplicit("C{$row}", (string) $value, DataType::TYPE_STRING);
            }
            $row++;
        }

        return $row + 1;
    }

    /** @return int the next free row */
    private function writeLines(Worksheet $sheet, $t, int $row): int
    {
        $sheet->setCellValue("A{$row}", 'EMPLOYEES');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $sheet->fromArray(['#', 'Employee', 'Staff ID', 'From outlet', 'Start', 'End', 'Basis', 'Days', 'Hours',
            'Rate (RM)', 'Salary (RM)', 'OT hrs', 'OT (RM)', 'Total (RM)'], null, "A{$row}");
        $sheet->getStyle("A{$row}:N{$row}")->getFont()->setBold(true);
        $this->band($sheet, "A{$row}:N{$row}", self::HEADER_FILL);
        $sheet->getStyle("H{$row}:N{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $first = ++$row;

        foreach ($t->lines as $i => $l) {
            $rate = match ($l->basis) {
                'hourly'  => (float) $l->hourly_rate,
                'ot_only' => null,
                default   => (float) $l->daily_rate,
            };

            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $l->employee_name);
            $sheet->setCellValueExplicit("C{$row}", (string) ($l->employee?->staff_id ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue("D{$row}", $l->fromOutlet?->name ?? '—');
            $sheet->setCellValue("E{$row}", ExcelDate::PHPToExcel($l->date_start));
            $sheet->setCellValue("F{$row}", ExcelDate::PHPToExcel($l->date_end));
            $sheet->setCellValue("G{$row}", LabourCostTransferLine::BASES[$l->basis] ?? $l->basis);
            $sheet->setCellValue("H{$row}", (float) $l->days);
            $sheet->setCellValue("I{$row}", (float) $l->hours);
            $sheet->setCellValue("J{$row}", $rate);
            $sheet->setCellValue("K{$row}", (float) $l->salary_amount);
            $sheet->setCellValue("L{$row}", (float) $l->ot_hours);
            $sheet->setCellValue("M{$row}", (float) $l->ot_amount);
            $sheet->setCellValue("N{$row}", "=K{$row}+M{$row}");
            $row++;
        }
        $last = $row - 1;

        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->mergeCells("A{$row}:G{$row}");
        foreach (['H', 'I', 'K', 'L', 'M', 'N'] as $col) {
            $sheet->setCellValue("{$col}{$row}", $last >= $first ? "=SUM({$col}{$first}:{$col}{$last})" : 0);
        }
        $sheet->getStyle("A{$row}:N{$row}")->getFont()->setBold(true);
        $this->band($sheet, "A{$row}:N{$row}", self::TOTAL_FILL);

        if ($last >= $first) {
            $sheet->getStyle("E{$first}:F{$last}")->getNumberFormat()->setFormatCode(self::DATE);
        }
        $sheet->getStyle("H{$first}:I{$row}")->getNumberFormat()->setFormatCode(self::QTY);
        $sheet->getStyle("L{$first}:L{$row}")->getNumberFormat()->setFormatCode(self::QTY);
        $sheet->getStyle("J{$first}:K{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("M{$first}:N{$row}")->getNumberFormat()->setFormatCode(self::MONEY);

        return $row + 2;
    }

    private function writeSummary(Worksheet $sheet, array $summary, $names, int $row): void
    {
        $sheet->setCellValue("A{$row}", 'SUMMARY BY OUTLET');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        // Laid over the same columns as the lines above, so widths suit both.
        $sheet->setCellValue("A{$row}", '');
        $sheet->setCellValue("B{$row}", 'Outlet');
        $sheet->setCellValue("C{$row}", 'Staff sent');
        $sheet->setCellValue("H{$row}", 'Days');
        $sheet->setCellValue("I{$row}", 'Hours');
        $sheet->setCellValue("K{$row}", 'Salary out');
        $sheet->setCellValue("L{$row}", 'OT hrs');
        $sheet->setCellValue("M{$row}", 'OT out');
        $sheet->setCellValue("N{$row}", 'Received');
        $sheet->setCellValue("O{$row}", 'Net (RM)');
        $sheet->getStyle("A{$row}:O{$row}")->getFont()->setBold(true);
        $this->band($sheet, "A{$row}:O{$row}", self::HEADER_FILL);
        $sheet->getStyle("H{$row}:O{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $first = ++$row;

        foreach ($summary as $s) {
            $sheet->setCellValue("B{$row}", $names[$s['outlet_id']] ?? '—');
            $sheet->setCellValue("C{$row}", $s['staff']);
            $sheet->setCellValue("H{$row}", $s['days']);
            $sheet->setCellValue("I{$row}", $s['hours']);
            $sheet->setCellValue("K{$row}", $s['salary_out']);
            $sheet->setCellValue("L{$row}", $s['ot_hours']);
            $sheet->setCellValue("M{$row}", $s['ot_out']);
            $sheet->setCellValue("N{$row}", $s['received']);
            // Live: what it received less what it sent out (salary + OT).
            $sheet->setCellValue("O{$row}", "=N{$row}-K{$row}-M{$row}");
            $row++;
        }
        $last = $row - 1;

        $sheet->setCellValue("B{$row}", 'TOTAL');
        foreach (['C', 'H', 'I', 'K', 'L', 'M', 'N', 'O'] as $col) {
            $sheet->setCellValue("{$col}{$row}", $last >= $first ? "=SUM({$col}{$first}:{$col}{$last})" : 0);
        }
        $sheet->getStyle("A{$row}:O{$row}")->getFont()->setBold(true);
        $this->band($sheet, "A{$row}:O{$row}", self::TOTAL_FILL);

        $sheet->getStyle("H{$first}:I{$row}")->getNumberFormat()->setFormatCode(self::QTY);
        $sheet->getStyle("L{$first}:L{$row}")->getNumberFormat()->setFormatCode(self::QTY);
        $sheet->getStyle("K{$first}:K{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("M{$first}:N{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("O{$first}:O{$row}")->getNumberFormat()->setFormatCode(self::NET);
        $sheet->getStyle("O{$first}:O{$row}")->getFont()->setBold(true);

        $row += 2;
        $sheet->setCellValue("A{$row}", 'Net: + takes the cost on, − hands it off; it nets to zero. Rates from the salary on file; overtime from approved claims settled in payroll.');
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setARGB('FF64748B');
    }
}
