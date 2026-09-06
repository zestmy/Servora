<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The workbook counterpart to pdf.inventory-group-summary — same data, same
 * shape (a range totalled into groups, each group's own records filed under
 * it), so the Wastage, Staff Meal and Transfer summaries never need three
 * separate spreadsheet layouts to stay in step with their three PDFs.
 *
 * Each group's subtotal is a live SUM over its own detail rows rather than a
 * copied number, the same reasoning as the consolidated stock take workbook:
 * a reader can check the total by eye instead of trusting the export blind.
 */
class GroupSummaryExcelWriter
{
    private const MONEY = '#,##0.00';

    private const HEADER_FILL = 'FFE5E7EB'; // gray-200
    private const GROUP_FILL  = 'FFF3F4F6'; // gray-100
    private const TOTAL_FILL  = 'FFE5E7EB';

    /**
     * @param array{
     *     docTitle: string, companyName: ?string, generatedBy: string,
     *     scopeRows: \Illuminate\Support\Collection<int, array{0:string,1:string}>,
     *     groupLabel: string, valueLabel: string, noun: string,
     *     groups: array<int, array{rank:int,name:string,count:int,value:float,share:float}>,
     *     totals: array{value:float,count:int,groups:int,average:float,days:int},
     *     detailBlocks: array<int, array{group: array, rows: \Illuminate\Support\Collection, more: int}>,
     *     detailColumns: array<int, array{label:string, align?:string, value: \Closure}>,
     * } $data
     */
    public function write(array $data): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator($data['generatedBy'])
            ->setTitle($data['docTitle']);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($data['groupLabel'] . ' Summary', 0, 31));

        $row = $this->writeHeader($sheet, $data);
        $row = $this->writeGroupTable($sheet, $data, $row);
        $this->writeDetailBlocks($sheet, $data, $row);

        return $spreadsheet;
    }

    private function writeHeader(Worksheet $sheet, array $data): int
    {
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(12);

        $sheet->setCellValue('A1', strtoupper($data['docTitle']));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $meta = array_merge(
            [['Company', $data['companyName'] ?? '—']],
            $data['scopeRows']->all(),
        );

        $row = 2;
        foreach ($meta as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $totals = $data['totals'];
        $sheet->setCellValue("A{$row}", 'Total ' . strtolower($data['valueLabel']) . ' (RM)');
        $sheet->setCellValue("B{$row}", round($totals['value'], 2));
        $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue("A{$row}", ucfirst(\Illuminate\Support\Str::plural($data['noun'], 2)));
        $sheet->setCellValue("B{$row}", $totals['count']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        return $row + 1;
    }

    /** @return int the next free row */
    private function writeGroupTable(Worksheet $sheet, array $data, int $row): int
    {
        $sheet->setCellValue("A{$row}", $data['groupLabel'] . ' Summary');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
        $row++;

        $headings = [$data['groupLabel'], ucfirst(\Illuminate\Support\Str::plural($data['noun'], 2)), $data['valueLabel'] . ' (RM)', 'Share'];
        $sheet->fromArray($headings, null, "A{$row}");
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $sheet->getStyle("B{$row}:D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $row++;

        $firstDataRow = $row;

        foreach ($data['groups'] as $g) {
            $sheet->setCellValue("A{$row}", $g['name']);
            $sheet->setCellValue("B{$row}", $g['count']);
            $sheet->setCellValue("C{$row}", round($g['value'], 2));
            $sheet->setCellValue("D{$row}", round($g['share'], 1) / 100);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('0.0%');
            $row++;
        }

        $lastDataRow = $row - 1;

        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->setCellValue("B{$row}", $lastDataRow >= $firstDataRow ? "=SUM(B{$firstDataRow}:B{$lastDataRow})" : 0);
        $sheet->setCellValue("C{$row}", $lastDataRow >= $firstDataRow ? "=SUM(C{$firstDataRow}:C{$lastDataRow})" : 0);
        $sheet->setCellValue("D{$row}", 1);
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::TOTAL_FILL);
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('0.0%');

        return $row + 2;
    }

    private function writeDetailBlocks(Worksheet $sheet, array $data, int $row): void
    {
        if (empty($data['detailBlocks'])) {
            return;
        }

        $columns    = $data['detailColumns'];
        $valueColIx = count($columns); // detail rows always end with the value column
        $valueCol   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($valueColIx);
        $lastCol    = $valueCol;

        foreach ($data['detailBlocks'] as $block) {
            $g = $block['group'];

            $sheet->setCellValue("A{$row}", $g['name']);
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::GROUP_FILL);
            $row++;

            $headings = array_column($columns, 'label');
            $sheet->fromArray($headings, null, "A{$row}");
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
            $row++;

            $firstDataRow = $row;

            foreach ($block['rows'] as $line) {
                foreach ($columns as $i => $col) {
                    $col_ = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                    $value = $col['value']($line);

                    if (($col['type'] ?? null) === 'number') {
                        $sheet->setCellValue("{$col_}{$row}", (float) $value);
                        // Only the final column — the one the block's subtotal
                        // sums — is money; an earlier numeric column (an item
                        // count, say) stays a plain number.
                        if ($i + 1 === $valueColIx) {
                            $sheet->getStyle("{$col_}{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
                        }
                    } else {
                        $sheet->setCellValueExplicit("{$col_}{$row}", (string) $value, DataType::TYPE_STRING);
                    }
                }
                $row++;
            }

            $lastDataRow = $row - 1;

            if ($block['more'] > 0) {
                $sheet->setCellValue("A{$row}", $block['more'] . ' earlier not listed — included in the subtotal below.');
                $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(8);
                $row++;
            }

            $sheet->setCellValue("A{$row}", 'Subtotal');
            $sheet->mergeCells("A{$row}:" . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($valueColIx - 1) . $row);
            $sheet->setCellValue("{$valueCol}{$row}", $lastDataRow >= $firstDataRow ? "=SUM({$valueCol}{$firstDataRow}:{$valueCol}{$lastDataRow})" : round((float) $g['value'], 2));
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
            $sheet->getStyle("{$valueCol}{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
            $row += 2;
        }
    }
}
