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
 * The labour cost transfer summary as a workbook.
 *
 * Extends the PDF controller so both are built from one load() — the same
 * confirmed transfers, the same period, the same outlet scope. Three sheets:
 *
 *   Net by Outlet — lent, borrowed and net per outlet; net and the totals are
 *                   live formulas, so the net column visibly sums to zero
 *   By Outlet     — the PDF's per-outlet sections: borrowed, then lent
 *   Lines         — every line flat, with a filter row, for pivoting
 *
 * Money in the line rows is live too: total = salary + OT.
 */
class LabourCostTransferSummaryExcelController extends LabourCostTransferSummaryPdfController
{
    private const MONEY = '#,##0.00';
    private const QTY   = '0.00';
    private const DATE  = 'dd mmm yyyy';

    private const HEADER_FILL  = 'FFE5E7EB';
    private const SECTION_FILL = 'FFF3F4F6';
    private const TOTAL_FILL   = 'FFE5E7EB';

    public function __invoke(Request $request)
    {
        $data  = $this->load($request);
        $scope = $data['scope'];

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(Auth::user()->name)
            ->setTitle('Labour Cost Transfer Summary ' . $scope['from'] . ' to ' . $scope['to']);

        $this->writeNetSheet($spreadsheet->getActiveSheet(), $data);
        $this->writeByOutletSheet($spreadsheet->createSheet(), $data);
        $this->writeLinesSheet($spreadsheet->createSheet(), $data);
        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'Labour-Cost-Transfer-Summary-' . $scope['from'] . '-to-' . $scope['to'] . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Title block shared by every sheet. @return int the next free row */
    private function writeTitle(Worksheet $sheet, string $title, array $data): int
    {
        $scope = $data['scope'];

        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $meta = [
            ['Company', $data['company']?->name ?? '—'],
            ['Period', $scope['from'] . ' to ' . $scope['to']],
            ['Outlet', $scope['outlet']],
            ['Transfers', $data['transfers']->count() . ' confirmed'
                . ($data['draftCount'] ? ' (' . $data['draftCount'] . ' draft not included)' : '')],
        ];

        $row = 2;
        foreach ($meta as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValueExplicit("B{$row}", (string) $value, DataType::TYPE_STRING);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        return $row + 1;
    }

    private function headings(Worksheet $sheet, int $row, array $headings, string $lastCol, string $numericFrom): void
    {
        $sheet->fromArray($headings, null, "A{$row}");
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $sheet->getStyle("{$numericFrom}{$row}:{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function fill(Worksheet $sheet, string $range, string $argb): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
    }

    // ── Sheet 1: net by outlet ────────────────────────────────────────────

    private function writeNetSheet(Worksheet $sheet, array $data): void
    {
        $sheet->setTitle('Net by Outlet');
        foreach (['A' => 28, 'B' => 11, 'C' => 9, 'D' => 9, 'E' => 9, 'F' => 14, 'G' => 14, 'H' => 14] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $row = $this->writeTitle($sheet, 'LABOUR COST TRANSFER — NET BY OUTLET', $data);
        $this->headings($sheet, $row, ['Outlet', 'Staff sent', 'Days', 'Hours', 'OT hrs', 'Lent (RM)', 'Borrowed (RM)', 'Net (RM)'], 'H', 'B');
        $sheet->freezePane('A' . ($row + 1));
        $first = ++$row;

        foreach ($data['summary'] as $s) {
            $sheet->setCellValue("A{$row}", $data['outletNames'][$s['outlet_id']] ?? '—');
            $sheet->setCellValue("B{$row}", $s['staff']);
            $sheet->setCellValue("C{$row}", $s['days']);
            $sheet->setCellValue("D{$row}", $s['hours']);
            $sheet->setCellValue("E{$row}", $s['ot_hours']);
            $sheet->setCellValue("F{$row}", $s['sent']);
            $sheet->setCellValue("G{$row}", $s['received']);
            // Live: borrowed minus lent, so the reader can see where it comes from.
            $sheet->setCellValue("H{$row}", "=G{$row}-F{$row}");
            $row++;
        }
        $last = $row - 1;

        $sheet->setCellValue("A{$row}", 'TOTAL');
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $sheet->setCellValue("{$col}{$row}", $last >= $first ? "=SUM({$col}{$first}:{$col}{$last})" : 0);
        }
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
        $this->fill($sheet, "A{$row}:H{$row}", self::TOTAL_FILL);

        $sheet->getStyle("C{$first}:E{$row}")->getNumberFormat()->setFormatCode(self::QTY);
        $sheet->getStyle("F{$first}:H{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("H{$first}:H{$row}")->getFont()->setBold(true);

        $row += 2;
        $sheet->setCellValue("A{$row}", 'Net: + takes the cost on (borrowed staff), − hands it off (lent staff). Across all outlets it nets to zero.');
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setARGB('FF64748B');
    }

    // ── Sheet 2: by outlet, borrowed and lent ─────────────────────────────

    private function writeByOutletSheet(Worksheet $sheet, array $data): void
    {
        $sheet->setTitle('By Outlet');
        foreach (['A' => 18, 'B' => 26, 'C' => 26, 'D' => 18, 'E' => 12, 'F' => 12, 'G' => 11, 'H' => 12, 'I' => 9, 'J' => 12, 'K' => 13] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $row = $this->writeTitle($sheet, 'LABOUR COST TRANSFER — BY OUTLET', $data);

        foreach ($data['sections'] as $section) {
            $sheet->setCellValue("A{$row}", strtoupper($section['name']));
            $sheet->mergeCells("A{$row}:I{$row}");
            $sheet->setCellValue("J{$row}", 'Net');
            $netRow = $row;
            $sheet->getStyle("A{$row}:K{$row}")->getFont()->setBold(true);
            $this->fill($sheet, "A{$row}:K{$row}", self::SECTION_FILL);
            $row++;

            $totals = [];
            foreach (['borrowed' => ['Borrowed — cost taken on', 'From'], 'lent' => ['Lent — cost handed off', 'To']] as $key => [$label, $other]) {
                if (! count($section[$key])) {
                    continue;
                }

                $sheet->setCellValue("A{$row}", $label);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setARGB('FF475569');
                $row++;

                $this->headings($sheet, $row, ['Transfer', 'Event / purpose', 'Employee', $other, 'Start', 'End', 'Basis', 'Salary', 'OT hrs', 'OT (RM)', 'Total (RM)'], 'K', 'H');
                $first = ++$row;

                foreach ($section[$key] as $r) {
                    /** @var LabourCostTransferLine $l */
                    $l = $r['line'];
                    $sheet->setCellValue("A{$row}", $r['transfer']);
                    $sheet->setCellValue("B{$row}", $r['purpose']);
                    $sheet->setCellValue("C{$row}", $r['employee']);
                    $sheet->setCellValue("D{$row}", $r['other']);
                    $sheet->setCellValue("E{$row}", ExcelDate::PHPToExcel($l->date_start));
                    $sheet->setCellValue("F{$row}", ExcelDate::PHPToExcel($l->date_end));
                    $sheet->setCellValue("G{$row}", $r['basis']);
                    $sheet->setCellValue("H{$row}", $r['salary']);
                    $sheet->setCellValue("I{$row}", $r['ot_hours']);
                    $sheet->setCellValue("J{$row}", $r['ot']);
                    $sheet->setCellValue("K{$row}", "=H{$row}+J{$row}");
                    $row++;
                }
                $last = $row - 1;

                $sheet->getStyle("E{$first}:F{$last}")->getNumberFormat()->setFormatCode(self::DATE);
                $sheet->getStyle("H{$first}:H{$last}")->getNumberFormat()->setFormatCode(self::MONEY);
                $sheet->getStyle("I{$first}:I{$last}")->getNumberFormat()->setFormatCode(self::QTY);
                $sheet->getStyle("J{$first}:K{$last}")->getNumberFormat()->setFormatCode(self::MONEY);

                $sheet->setCellValue("A{$row}", $key === 'lent' ? 'Total lent' : 'Total borrowed');
                $sheet->setCellValue("K{$row}", "=SUM(K{$first}:K{$last})");
                $sheet->getStyle("A{$row}:K{$row}")->getFont()->setBold(true);
                $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
                $this->fill($sheet, "A{$row}:K{$row}", self::TOTAL_FILL);
                $totals[$key] = "K{$row}";
                $row += 2;
            }

            // Section net, live over the two subtotals.
            $sheet->setCellValue("K{$netRow}", '=' . ($totals['borrowed'] ?? '0') . '-' . ($totals['lent'] ?? '0'));
            $sheet->getStyle("K{$netRow}")->getNumberFormat()->setFormatCode('+#,##0.00;−#,##0.00;0.00');
        }
    }

    // ── Sheet 3: every line, filterable ───────────────────────────────────

    private function writeLinesSheet(Worksheet $sheet, array $data): void
    {
        $sheet->setTitle('Lines');
        $widths = ['A' => 18, 'B' => 12, 'C' => 16, 'D' => 26, 'E' => 18, 'F' => 26, 'G' => 11, 'H' => 18,
                   'I' => 12, 'J' => 12, 'K' => 11, 'L' => 8, 'M' => 8, 'N' => 11, 'O' => 12, 'P' => 8, 'Q' => 12, 'R' => 13];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $row = $this->writeTitle($sheet, 'LABOUR COST TRANSFER — LINES', $data);
        $headRow = $row;
        $this->headings($sheet, $row, [
            'Transfer', 'Transfer date', 'Purpose', 'Event / reference', 'Charged to', 'Employee', 'Staff ID', 'From outlet',
            'Start', 'End', 'Basis', 'Days', 'Hours', 'Rate (RM)', 'Salary (RM)', 'OT hrs', 'OT (RM)', 'Total (RM)',
        ], 'R', 'L');
        $sheet->freezePane('A' . ($row + 1));
        $first = ++$row;

        foreach ($data['transfers'] as $t) {
            foreach ($t->lines as $l) {
                $rate = match ($l->basis) {
                    'hourly'  => (float) $l->hourly_rate,
                    'ot_only' => null,
                    default   => (float) $l->daily_rate,
                };

                $sheet->setCellValue("A{$row}", $t->transfer_number);
                $sheet->setCellValue("B{$row}", ExcelDate::PHPToExcel($t->transfer_date));
                $sheet->setCellValue("C{$row}", $t->purposeLabel());
                $sheet->setCellValue("D{$row}", (string) $t->reference);
                $sheet->setCellValue("E{$row}", $t->toOutlet?->name ?? '—');
                $sheet->setCellValue("F{$row}", $l->employee_name);
                $sheet->setCellValueExplicit("G{$row}", (string) ($l->employee?->staff_id ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValue("H{$row}", $l->fromOutlet?->name ?? '—');
                $sheet->setCellValue("I{$row}", ExcelDate::PHPToExcel($l->date_start));
                $sheet->setCellValue("J{$row}", ExcelDate::PHPToExcel($l->date_end));
                $sheet->setCellValue("K{$row}", LabourCostTransferLine::BASES[$l->basis] ?? $l->basis);
                $sheet->setCellValue("L{$row}", (float) $l->days);
                $sheet->setCellValue("M{$row}", (float) $l->hours);
                $sheet->setCellValue("N{$row}", $rate);
                $sheet->setCellValue("O{$row}", (float) $l->salary_amount);
                $sheet->setCellValue("P{$row}", (float) $l->ot_hours);
                $sheet->setCellValue("Q{$row}", (float) $l->ot_amount);
                $sheet->setCellValue("R{$row}", "=O{$row}+Q{$row}");
                $row++;
            }
        }
        $last = $row - 1;

        if ($last >= $first) {
            $sheet->setAutoFilter("A{$headRow}:R{$last}");
            $sheet->getStyle("B{$first}:B{$last}")->getNumberFormat()->setFormatCode(self::DATE);
            $sheet->getStyle("I{$first}:J{$last}")->getNumberFormat()->setFormatCode(self::DATE);
        }

        $sheet->setCellValue("A{$row}", 'TOTAL');
        foreach (['L', 'M', 'O', 'P', 'Q', 'R'] as $col) {
            $sheet->setCellValue("{$col}{$row}", $last >= $first ? "=SUBTOTAL(9,{$col}{$first}:{$col}{$last})" : 0);
        }
        $sheet->getStyle("A{$row}:R{$row}")->getFont()->setBold(true);
        $this->fill($sheet, "A{$row}:R{$row}", self::TOTAL_FILL);

        $sheet->getStyle("L{$first}:M{$row}")->getNumberFormat()->setFormatCode(self::QTY);
        $sheet->getStyle("P{$first}:P{$row}")->getNumberFormat()->setFormatCode(self::QTY);
        $sheet->getStyle("N{$first}:O{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("Q{$first}:R{$row}")->getNumberFormat()->setFormatCode(self::MONEY);

        $row += 2;
        $sheet->setCellValue("A{$row}", 'Totals use SUBTOTAL, so they follow the filter.');
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setARGB('FF64748B');
    }
}
