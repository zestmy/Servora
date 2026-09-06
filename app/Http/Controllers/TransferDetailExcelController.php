<?php

namespace App\Http\Controllers;

use App\Services\TransferConsolidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The same transfer detail report as a workbook.
 *
 * Extends the PDF controller so both are built from one load() — see
 * WastageDetailExcelController for the same reasoning. The value column is
 * a live formula over the quantity and rate beside it, and the category and
 * grand totals are live SUMs over the item rows.
 */
class TransferDetailExcelController extends TransferDetailController
{
    private const MONEY  = '#,##0.00';
    private const MONEY4 = '#,##0.0000';
    private const QTY    = '0.####';

    private const HEADER_FILL   = 'FFE5E7EB';
    private const CATEGORY_FILL = 'FFF3F4F6';
    private const TOTAL_FILL    = 'FFE5E7EB';

    public function __invoke(Request $request, TransferConsolidator $consolidator)
    {
        [$report, $company, $scope] = $this->load($request, $consolidator);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(Auth::user()->name)
            ->setTitle('Transfer Details ' . $scope['from'] . ' to ' . $scope['to']);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Transfer Details');

        $row = $this->writeHeader($sheet, $company?->name, $scope, $report);
        [$row, $blocks] = $this->writeItems($sheet, $report['groups'], $row);
        $this->writeTotal($sheet, $row, $report['total'], $blocks);

        $this->writeTransfersSheet($spreadsheet, $report);

        $filename = 'Transfer-Details-' . $scope['from'] . '-to-' . $scope['to'] . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @return int the next free row */
    private function writeHeader(Worksheet $sheet, ?string $companyName, array $scope, array $report): int
    {
        foreach (['A' => 34, 'B' => 12, 'C' => 8, 'D' => 12, 'E' => 12, 'F' => 12] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->setCellValue('A1', 'TRANSFER DETAILS');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $meta = [
            ['Company', $companyName ?? '—'],
            ['Period', $scope['from'] . ' to ' . $scope['to']],
            ['Outlet', $scope['outlet']],
            ['Status', $scope['status']],
            ['Transfers merged', (string) $report['transfers']->count()],
        ];

        $row = 2;
        foreach ($meta as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $row++;

        $headings = ['Item', 'Code', 'UOM', 'Quantity', 'Unit Cost', 'Value (RM)'];
        $sheet->fromArray($headings, null, "A{$row}");
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:F{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $sheet->getStyle("D{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->freezePane('A' . ($row + 1));

        return $row + 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array{0: int, 1: array<int, string>}
     */
    private function writeItems(Worksheet $sheet, array $groups, int $row): array
    {
        $blocks = [];

        foreach ($groups as $group) {
            $sheet->setCellValue("A{$row}", strtoupper($group['name']) . ' (' . count($group['items']) . ')');
            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("F{$row}", '=SUM(F' . ($row + 1) . ':F' . ($row + count($group['items'])) . ')');
            $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:F{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CATEGORY_FILL);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
            $row++;

            $blocks[] = [$row, $row + count($group['items']) - 1];

            foreach ($group['items'] as $item) {
                $sheet->setCellValue("A{$row}", $item['name']);
                $sheet->setCellValueExplicit("B{$row}", (string) ($item['code'] ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValue("C{$row}", $item['uom_abbr']);
                $sheet->setCellValue("D{$row}", $item['quantity']);
                $sheet->setCellValue("E{$row}", $item['unit_cost']);
                $sheet->setCellValue("F{$row}", "=D{$row}*E{$row}");

                $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::QTY);
                $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode(self::MONEY4);
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
                $row++;
            }
        }

        return [$row, $blocks];
    }

    /** @param array<int, array{0:int,1:int}> $blocks item-row spans, one per category */
    private function writeTotal(Worksheet $sheet, int $row, float $total, array $blocks): void
    {
        $sum = $blocks
            ? '=SUM(' . implode(',', array_map(fn ($b) => "F{$b[0]}:F{$b[1]}", $blocks)) . ')'
            : $total;

        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->setCellValue("F{$row}", $sum);
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:F{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::TOTAL_FILL);
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
    }

    /** The transfers the item sheet was merged from. */
    private function writeTransfersSheet(Spreadsheet $spreadsheet, array $report): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Transfers Merged');

        foreach (['A' => 12, 'B' => 16, 'C' => 20, 'D' => 20, 'E' => 14] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $headings = ['Date', 'Transfer #', 'From', 'To', 'Status'];
        $sheet->fromArray($headings, null, 'A1');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $sheet->freezePane('A2');

        $row = 2;
        foreach ($report['transfers'] as $transfer) {
            $sheet->setCellValue("A{$row}", $transfer->transfer_date?->format('d M Y') ?? '—');
            $sheet->setCellValueExplicit("B{$row}", (string) ($transfer->transfer_number ?: 'T-' . $transfer->id), DataType::TYPE_STRING);
            $sheet->setCellValue("C{$row}", $transfer->fromOutlet?->name ?? '—');
            $sheet->setCellValue("D{$row}", $transfer->toOutlet?->name ?? '—');
            $sheet->setCellValue("E{$row}", ucfirst(str_replace('_', ' ', $transfer->status)));
            $row++;
        }

        $spreadsheet->setActiveSheetIndex(0);
    }
}
