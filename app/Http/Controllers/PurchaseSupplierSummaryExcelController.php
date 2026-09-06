<?php

namespace App\Http\Controllers;

use App\Models\PurchaseCapture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The same "Purchases by Supplier" report as a workbook.
 *
 * Extends the PDF controller so both are built from one load() and cannot
 * drift into disagreeing about the same range — see
 * ConsolidatedStockTakeExcelController for the same reasoning. The share and
 * average columns are live formulas over the spend beside them, the way the
 * consolidated stock take and recipe cost exports work, so re-sorting or
 * spot-checking a number in Excel doesn't mean trusting a number this export
 * already baked in.
 */
class PurchaseSupplierSummaryExcelController extends PurchaseSupplierSummaryController
{
    private const MONEY  = '#,##0.00';
    private const HEADER_FILL = 'FFE5E7EB';
    private const GROUP_FILL  = 'FFF3F4F6';
    private const TOTAL_FILL  = 'FFE5E7EB';

    public function __invoke(Request $request)
    {
        $data = $this->load($request);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(Auth::user()->name)
            ->setTitle('Purchases by Supplier ' . $data['scope']['from'] . ' to ' . $data['scope']['to']);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Purchases by Supplier');

        $row = $this->writeHeader($sheet, $data);
        $row = $this->writeSupplierTable($sheet, $data, $row);
        $this->writeDetailBlocks($sheet, $data, $row);

        $filename = 'Purchases-by-Supplier-' . $data['scope']['from'] . '-to-' . $data['scope']['to'] . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function writeHeader(Worksheet $sheet, array $data): int
    {
        $sheet->getColumnDimension('A')->setWidth(30);
        foreach (['B', 'C', 'D', 'E'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(16);
        }

        $sheet->setCellValue('A1', 'PURCHASES BY SUPPLIER');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $meta = [
            ['Company', $data['company']?->name ?? '—'],
            ['Period', $data['scope']['from'] . ' to ' . $data['scope']['to']],
            ['Outlet', $data['scope']['outlet']],
            ['Department', $data['scope']['department']],
            ['Supplier', $data['scope']['supplier']],
        ];

        $row = 2;
        foreach ($meta as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Total spend (RM)');
        $sheet->setCellValue("B{$row}", round($data['totals']['spend'], 2));
        $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue("A{$row}", 'Purchases');
        $sheet->setCellValue("B{$row}", $data['totals']['purchases']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        return $row + 2;
    }

    /** @return int the next free row */
    private function writeSupplierTable(Worksheet $sheet, array $data, int $row): int
    {
        $sheet->setCellValue("A{$row}", 'Supplier Summary');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
        $row++;

        $headings = ['Supplier', 'Buys', 'Spend (RM)', 'Share', 'Average (RM)', 'First', 'Last'];
        $sheet->fromArray($headings, null, "A{$row}");
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $sheet->getStyle("B{$row}:E{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $row++;

        // Fixed offset from the first data row, known before any row is
        // written — so the per-row share formula can point straight at it
        // instead of being backfilled in a second pass.
        $first    = $row;
        $totalRow = $first + count($data['suppliers']);

        foreach ($data['suppliers'] as $s) {
            $sheet->setCellValue("A{$row}", $s['name'] . ($s['supplier_id'] ? '' : ' (unlinked)'));
            $sheet->setCellValue("B{$row}", $s['purchases']);
            $sheet->setCellValue("C{$row}", round($s['spend'], 2));
            // Share is derived from spend and the grand total below, not copied.
            $sheet->setCellValue("D{$row}", "=C{$row}/C\${$totalRow}");
            $sheet->setCellValue("E{$row}", "=IF(B{$row}=0,0,C{$row}/B{$row})");
            $sheet->setCellValue("F{$row}", \Illuminate\Support\Carbon::parse($s['first_at'])->format('d M Y'));
            $sheet->setCellValue("G{$row}", \Illuminate\Support\Carbon::parse($s['last_at'])->format('d M Y'));
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('0.0%');
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
            $row++;
        }

        $last = $row - 1;

        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->setCellValue("B{$row}", $last >= $first ? "=SUM(B{$first}:B{$last})" : 0);
        $sheet->setCellValue("C{$row}", $last >= $first ? "=SUM(C{$first}:C{$last})" : 0);
        $sheet->setCellValue("D{$row}", 1);
        $sheet->setCellValue("E{$row}", "=IF(B{$row}=0,0,C{$row}/B{$row})");
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::TOTAL_FILL);
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('0.0%');
        $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode(self::MONEY);

        return $row + 2;
    }

    private function writeDetailBlocks(Worksheet $sheet, array $data, int $row): void
    {
        $details = $data['details'];

        if ($details['omitted'] || empty($details['blocks'])) {
            return;
        }

        foreach ($details['blocks'] as $block) {
            $s = $block['supplier'];

            $sheet->setCellValue("A{$row}", $s['name']);
            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::GROUP_FILL);
            $row++;

            $sheet->fromArray(['Date', 'Reference', 'Department', 'Outlet', 'Amount (RM)'], null, "A{$row}");
            $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
            $row++;

            $first = $row;

            foreach ($block['rows'] as $line) {
                $sheet->setCellValue("A{$row}", $line->purchase_date?->format('d M Y') ?? '—');
                $sheet->setCellValueExplicit("B{$row}", (string) ($line->reference_number ?: '—'), DataType::TYPE_STRING);
                $sheet->setCellValue("C{$row}", $line->department?->name ?? '—');
                $sheet->setCellValue("D{$row}", $line->outlet?->name ?? '—');
                $sheet->setCellValue("E{$row}", (float) $line->amount);
                $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
                $row++;
            }

            $last = $row - 1;

            if ($block['more'] > 0) {
                $sheet->setCellValue("A{$row}", $block['more'] . ' earlier not listed — included in the subtotal below.');
                $sheet->mergeCells("A{$row}:E{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(8);
                $row++;
            }

            $sheet->setCellValue("A{$row}", 'Subtotal');
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->setCellValue("E{$row}", $last >= $first ? "=SUM(E{$first}:E{$last})" : round((float) $s['spend'], 2));
            $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
            $row += 2;
        }
    }
}
