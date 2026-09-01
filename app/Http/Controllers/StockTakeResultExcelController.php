<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The same finished count as a workbook.
 *
 * Extends the PDF controller so both are built from one loader and cannot drift
 * into disagreeing about the same count.
 *
 * The value, variance and totals columns are live formulas over the quantities
 * and rates beside them, the way the recipe cost export works. Somebody filing
 * a count is usually about to ask "what if this rate is wrong" or "what does
 * this group come to on its own", and a workbook of dead numbers cannot answer
 * either — this one recalculates when a cell is changed.
 */
class StockTakeResultExcelController extends StockTakeResultController
{
    private const MONEY  = '#,##0.00';
    private const MONEY4 = '#,##0.0000';
    private const QTY    = '0.####';

    private const HEADER_FILL   = 'FFE5E7EB'; // gray-200
    private const CATEGORY_FILL = 'FFF3F4F6'; // gray-100
    private const TOTAL_FILL    = 'FFE5E7EB';

    public function __invoke(int $id)
    {
        [$stockTake, $groups, $totals] = $this->result($id);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(Auth::user()->name)
            ->setTitle('Stock Take ' . ($stockTake->reference_number ?: $stockTake->id));

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stock Take');

        $row = $this->writeHeader($sheet, $stockTake);
        [$row, $itemRanges] = $this->writeItems($sheet, $groups, $row);
        $this->writeTotals($sheet, $row, $totals, $itemRanges);

        $filename = 'Stock-Take-' . $this->reference($stockTake) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            // The formulas are the point; let Excel evaluate them on open.
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @return int the next free row */
    private function writeHeader(Worksheet $sheet, $stockTake): int
    {
        foreach (['A' => 38, 'B' => 12, 'C' => 8, 'D' => 12, 'E' => 12, 'F' => 12, 'G' => 12, 'H' => 14, 'I' => 14] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->setCellValue('A1', 'STOCK TAKE');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $meta = [
            ['Reference', $stockTake->reference_number ?: 'ST-' . $stockTake->id],
            ['Date',      $stockTake->stock_take_date?->format('d M Y') ?? '—'],
            ['Outlet',    $stockTake->outlet?->name ?? '—'],
            ['Department', $stockTake->department?->name ?? 'All'],
            ['Counted by', $stockTake->createdBy?->name ?? '—'],
            ['Status',    ucfirst($stockTake->status)],
        ];

        $row = 2;
        foreach ($meta as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $row++;

        $headings = ['Item', 'Code', 'UOM', 'Expected', 'Counted', 'Variance', 'Unit Cost', 'Stock Value', 'Variance Cost'];
        $sheet->fromArray($headings, null, "A{$row}");
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:I{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $sheet->getStyle("D{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->freezePane("A" . ($row + 1));

        return $row + 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array{0: int, 1: array<int, string>} next free row, and the item-row
     *         blocks — the totals sum those explicitly rather than the whole column,
     *         which would count every value twice over the category subtotals.
     */
    private function writeItems(Worksheet $sheet, array $groups, int $row): array
    {
        $blocks = [];

        foreach ($groups as $group) {
            $sheet->setCellValue("A{$row}", strtoupper($group['name']) . ' (' . count($group['items']) . ')');
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("H{$row}", '=SUM(H' . ($row + 1) . ':H' . ($row + count($group['items'])) . ')');
            $sheet->setCellValue("I{$row}", '=SUM(I' . ($row + 1) . ':I' . ($row + count($group['items'])) . ')');
            $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:I{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CATEGORY_FILL);
            $sheet->getStyle("H{$row}:I{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
            $row++;

            $blocks[] = [$row, $row + count($group['items']) - 1];

            foreach ($group['items'] as $item) {
                $sheet->setCellValue("A{$row}", $item['sub'] ? $item['sub'] . ' · ' . $item['name'] : $item['name']);
                $sheet->setCellValueExplicit("B{$row}", (string) ($item['code'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue("C{$row}", $item['uom']);
                $sheet->setCellValue("D{$row}", $item['system']);
                $sheet->setCellValue("E{$row}", $item['counted']);
                // Derived, not copied: change a count and the rest follows.
                $sheet->setCellValue("F{$row}", "=E{$row}-D{$row}");
                $sheet->setCellValue("G{$row}", $item['unit_cost']);
                $sheet->setCellValue("H{$row}", "=E{$row}*G{$row}");
                $sheet->setCellValue("I{$row}", "=F{$row}*G{$row}");

                $sheet->getStyle("D{$row}:F{$row}")->getNumberFormat()->setFormatCode(self::QTY);
                $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode(self::MONEY4);
                $sheet->getStyle("H{$row}:I{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
                $row++;
            }
        }

        return [$row, $blocks];
    }

    /**
     * @param array<string, mixed>   $totals
     * @param array<int, array{0:int,1:int}> $blocks item-row spans, one per category
     */
    private function writeTotals(Worksheet $sheet, int $row, array $totals, array $blocks): void
    {
        $sum = fn (string $col) => $blocks
            ? '=SUM(' . implode(',', array_map(fn ($b) => "{$col}{$b[0]}:{$col}{$b[1]}", $blocks)) . ')'
            : '0';

        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->setCellValue("H{$row}", $sum('H'));
        $sheet->setCellValue("I{$row}", $sum('I'));
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:I{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::TOTAL_FILL);
        $sheet->getStyle("H{$row}:I{$row}")->getNumberFormat()->setFormatCode(self::MONEY);

        $row += 2;
        foreach ([
            ['Items counted', $totals['items']],
            ['Items over', $totals['over']],
            ['Items short', $totals['short']],
        ] as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }
    }
}
