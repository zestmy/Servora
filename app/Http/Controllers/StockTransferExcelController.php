<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * One outlet stock transfer as a workbook — the same note as the PDF, built
 * from the same load() (this extends the PDF controller). The line value is a
 * live formula over quantity and unit cost, and the total a live SUM.
 *
 * Period-level exports (Transfer Summary, Transfer Details) already exist on
 * the Stock Transfers tab; this is the single-transfer one.
 */
class StockTransferExcelController extends StockTransferPdfController
{
    private const MONEY  = '#,##0.00';
    private const MONEY4 = '#,##0.0000';
    private const QTY    = '#,##0.00##';

    private const HEADER_FILL = 'FFE5E7EB';
    private const TOTAL_FILL  = 'FFE5E7EB';

    private const STATUSES = [
        'draft' => 'Draft', 'in_transit' => 'In Transit', 'received' => 'Received', 'cancelled' => 'Cancelled',
    ];

    public function __invoke(Request $request, int $id)
    {
        ['transfer' => $t, 'company' => $company] = $this->load($request, $id);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(Auth::user()->name)
            ->setTitle('Stock Transfer ' . $t->transfer_number);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stock Transfer');

        foreach (['A' => 6, 'B' => 36, 'C' => 12, 'D' => 12, 'E' => 11, 'F' => 8, 'G' => 13, 'H' => 14] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $sheet->setCellValue('A1', 'STOCK TRANSFER NOTE');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $meta = [
            ['Company', $company?->name ?? '—'],
            ['Transfer #', $t->transfer_number],
            ['Status', self::STATUSES[$t->status] ?? ucfirst((string) $t->status)],
            ['Date', null],
            ['From', $t->fromOutlet?->name ?? '—'],
            ['To', $t->toOutlet?->name ?? '—'],
            ['Raised by', $t->createdBy?->name ?? '—'],
        ];
        if ($t->notes) {
            $meta[] = ['Notes', $t->notes];
        }

        $row = 2;
        foreach ($meta as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->mergeCells("A{$row}:B{$row}");
            if ($label === 'Date') {
                $sheet->setCellValue("C{$row}", ExcelDate::PHPToExcel($t->transfer_date));
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('dd mmm yyyy');
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            } else {
                $sheet->setCellValueExplicit("C{$row}", (string) $value, DataType::TYPE_STRING);
            }
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $row++;
        $headRow = $row;
        $sheet->fromArray(['#', 'Item', 'Type', 'Code', 'Quantity', 'UOM', 'Unit Cost', 'Value (RM)'], null, "A{$row}");
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $sheet->getStyle("E{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->freezePane('A' . ($row + 1));
        $first = ++$row;

        foreach ($t->lines as $i => $l) {
            $type = match (true) {
                (bool) $l->ingredient?->is_prep => 'Prep item',
                (bool) $l->recipe_id            => 'Recipe',
                ! $l->ingredient_id             => 'Custom',
                default                         => 'Market List',
            };

            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $l->item_name);
            $sheet->setCellValue("C{$row}", $type);
            $sheet->setCellValueExplicit("D{$row}", (string) ($l->ingredient?->code ?? $l->recipe?->code ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue("E{$row}", (float) $l->quantity);
            $sheet->setCellValue("F{$row}", $l->uom?->abbreviation ?? '');
            $sheet->setCellValue("G{$row}", (float) $l->unit_cost);
            $sheet->setCellValue("H{$row}", "=E{$row}*G{$row}");
            $row++;
        }
        $last = $row - 1;

        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("H{$row}", $last >= $first ? "=SUM(H{$first}:H{$last})" : 0);
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::TOTAL_FILL);

        $sheet->getStyle("E{$first}:E{$row}")->getNumberFormat()->setFormatCode(self::QTY);
        $sheet->getStyle("G{$first}:G{$row}")->getNumberFormat()->setFormatCode(self::MONEY4);
        $sheet->getStyle("H{$first}:H{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        if ($last >= $first) {
            $sheet->setAutoFilter("A{$headRow}:H{$last}");
        }

        $row += 2;
        $sheet->setCellValue("A{$row}", 'Recipe and custom items are recorded for value only and do not move stock on hand.');
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setARGB('FF64748B');

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, 'Stock-Transfer-' . $t->transfer_number . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
