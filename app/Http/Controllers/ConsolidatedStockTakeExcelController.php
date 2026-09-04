<?php

namespace App\Http\Controllers;

use App\Services\StockTakeConsolidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The same consolidated inventory as a workbook, one column per source sheet.
 *
 * The PDF answers "what does the outlet hold"; this answers "how did we get
 * that number" — every count that fed a row sits beside it, so a reader can
 * add them up by eye (or let the sheet do it: the consolidated column is a
 * live SUM over the take columns) instead of trusting the merge blind.
 *
 * Extends the PDF controller so both are built from one loader and cannot
 * drift into disagreeing about the same range.
 */
class ConsolidatedStockTakeExcelController extends ConsolidatedStockTakeController
{
    private const MONEY  = '#,##0.00';
    private const MONEY4 = '#,##0.0000';
    private const QTY    = '0.####';

    private const HEADER_FILL   = 'FFE5E7EB'; // gray-200
    private const CATEGORY_FILL = 'FFF3F4F6'; // gray-100
    private const TOTAL_FILL    = 'FFE5E7EB';

    private const FIRST_TAKE_COL = 4; // A=item, B=code, C=uom, D..=one per take

    public function __invoke(Request $request, StockTakeConsolidator $consolidator)
    {
        [$report, $company, $scope, $excludedDrafts, $from, $to] = $this->load($request, $consolidator);

        $takes = $report['takes']->values();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(Auth::user()->name)
            ->setTitle('Consolidated Inventory ' . $from . ' to ' . $to);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Consolidated Inventory');

        $row = $this->writeHeader($sheet, $company?->name, $scope, $excludedDrafts, $takes);
        [$row, $blocks] = $this->writeItems($sheet, $report['groups'], $takes, $row);
        $this->writeTotal($sheet, $row, $report['total'], $blocks, $takes);

        $filename = 'Consolidated-Inventory-' . $from . '-to-' . $to . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            // The per-take breakdown is the point; let Excel total it on open.
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\StockTake>  $takes
     * @return int the next free row
     */
    private function writeHeader(Worksheet $sheet, ?string $companyName, array $scope, int $excludedDrafts, $takes): int
    {
        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(8);
        foreach ($takes as $i => $take) {
            $sheet->getColumnDimensionByColumn(self::FIRST_TAKE_COL + $i)->setWidth(12);
        }

        $sheet->setCellValue('A1', 'CONSOLIDATED INVENTORY');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $meta = [
            ['Company',    $companyName ?? '—'],
            ['Period',     $scope['from'] . ' to ' . $scope['to']],
            ['Outlet',     $scope['outlet']],
            ['Department', $scope['department']],
            ['Sheets consolidated', (string) $takes->count()],
        ];

        if ($excludedDrafts > 0) {
            $meta[] = ['Drafts excluded', (string) $excludedDrafts . ' (not yet completed)'];
        }

        $row = 2;
        foreach ($meta as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $row++;

        // Two-row header for the take columns: which sheet, then what it was.
        $sheet->setCellValue("A{$row}", 'Item');
        $sheet->setCellValue("B{$row}", 'Code');
        $sheet->setCellValue("C{$row}", 'UOM');
        $sheet->mergeCells("A{$row}:A" . ($row + 1));
        $sheet->mergeCells("B{$row}:B" . ($row + 1));
        $sheet->mergeCells("C{$row}:C" . ($row + 1));

        foreach ($takes as $i => $take) {
            $col = $this->columnFor(self::FIRST_TAKE_COL + $i);
            $sheet->setCellValue("{$col}{$row}", $take->outlet?->name ?? '—');
            $sheet->setCellValue("{$col}" . ($row + 1), ($take->stock_take_date?->format('d M') ?? '—')
                . "\n" . ($take->reference_number ?: 'ST-' . $take->id));
            $sheet->getStyle("{$col}" . ($row + 1))->getAlignment()->setWrapText(true);
        }

        $qtyCol   = $this->columnFor(self::FIRST_TAKE_COL + count($takes));
        $costCol  = $this->columnFor(self::FIRST_TAKE_COL + count($takes) + 1);
        $valueCol = $this->columnFor(self::FIRST_TAKE_COL + count($takes) + 2);

        $sheet->setCellValue("{$qtyCol}{$row}", 'Consolidated Qty');
        $sheet->setCellValue("{$costCol}{$row}", 'Unit Cost');
        $sheet->setCellValue("{$valueCol}{$row}", 'Stock Value');
        $sheet->mergeCells("{$qtyCol}{$row}:{$qtyCol}" . ($row + 1));
        $sheet->mergeCells("{$costCol}{$row}:{$costCol}" . ($row + 1));
        $sheet->mergeCells("{$valueCol}{$row}:{$valueCol}" . ($row + 1));
        $sheet->getColumnDimension($qtyCol)->setWidth(14);
        $sheet->getColumnDimension($costCol)->setWidth(12);
        $sheet->getColumnDimension($valueCol)->setWidth(14);

        $headerRange = "A{$row}:{$valueCol}" . ($row + 1);
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->freezePane($this->columnFor(self::FIRST_TAKE_COL) . ($row + 2));
        $sheet->getRowDimension($row + 1)->setRowHeight(28);

        return $row + 2;
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @param  \Illuminate\Support\Collection<int, \App\Models\StockTake>  $takes
     * @return array{0: int, 1: array<int, array{0:int,1:int}>} next free row, and
     *         the item-row spans — the grand total sums those explicitly rather
     *         than the whole column, which would double-count category subtotals.
     */
    private function writeItems(Worksheet $sheet, array $groups, $takes, int $row): array
    {
        $qtyCol   = $this->columnFor(self::FIRST_TAKE_COL + count($takes));
        $costCol  = $this->columnFor(self::FIRST_TAKE_COL + count($takes) + 1);
        $valueCol = $this->columnFor(self::FIRST_TAKE_COL + count($takes) + 2);
        $lastCol  = $valueCol;

        $blocks = [];

        foreach ($groups as $group) {
            $sheet->setCellValue("A{$row}", strtoupper($group['name']) . ' (' . count($group['items']) . ')');
            $sheet->mergeCells("A{$row}:{$costCol}{$row}");
            $sheet->setCellValue("{$valueCol}{$row}", '=SUM(' . $valueCol . ($row + 1) . ':' . $valueCol . ($row + count($group['items'])) . ')');
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CATEGORY_FILL);
            $sheet->getStyle("{$valueCol}{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
            $row++;

            $blocks[] = [$row, $row + count($group['items']) - 1];

            foreach ($group['items'] as $item) {
                $sheet->setCellValue("A{$row}", $item['name']);
                $sheet->setCellValueExplicit("B{$row}", (string) ($item['code'] ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValue("C{$row}", $item['uom_abbr']);

                foreach ($takes as $i => $take) {
                    $col = $this->columnFor(self::FIRST_TAKE_COL + $i);
                    $qty = $item['byTake'][$take->id] ?? null;
                    if ($qty !== null) {
                        $sheet->setCellValue("{$col}{$row}", $qty);
                        $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode(self::QTY);
                    }
                }

                $takeCols = $this->columnFor(self::FIRST_TAKE_COL) . $row . ':'
                    . $this->columnFor(self::FIRST_TAKE_COL + count($takes) - 1) . $row;

                // Derived, not copied: the consolidated qty is the row of take
                // columns adding up, in the cell, so a reader can see it happen.
                $sheet->setCellValue("{$qtyCol}{$row}", count($takes) > 0 ? "=SUM({$takeCols})" : $item['quantity']);
                $sheet->setCellValue("{$costCol}{$row}", $item['unit_cost']);
                $sheet->setCellValue("{$valueCol}{$row}", "={$qtyCol}{$row}*{$costCol}{$row}");

                $sheet->getStyle("{$qtyCol}{$row}")->getNumberFormat()->setFormatCode(self::QTY);
                $sheet->getStyle("{$costCol}{$row}")->getNumberFormat()->setFormatCode(self::MONEY4);
                $sheet->getStyle("{$valueCol}{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
                $row++;
            }
        }

        return [$row, $blocks];
    }

    /** @param array<int, array{0:int,1:int}> $blocks item-row spans, one per category */
    private function writeTotal(Worksheet $sheet, int $row, float $total, array $blocks, $takes): void
    {
        $valueCol = $this->columnFor(self::FIRST_TAKE_COL + count($takes) + 2);
        $lastCol  = $valueCol;

        $sum = $blocks
            ? '=SUM(' . implode(',', array_map(fn ($b) => "{$valueCol}{$b[0]}:{$valueCol}{$b[1]}", $blocks)) . ')'
            : $total;

        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->setCellValue("{$valueCol}{$row}", $sum);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::TOTAL_FILL);
        $sheet->getStyle("{$valueCol}{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
    }

    /** 1-indexed column number to an Excel column letter. */
    private function columnFor(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
    }
}
