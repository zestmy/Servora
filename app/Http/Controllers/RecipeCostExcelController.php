<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\RecipePriceClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel (xlsx) cost export for Recipes and Prep Items.
 *
 * Mirrors the PDF cost exports (same query-string filters, sort, and cost
 * math via the shared parent methods) but writes a workbook with LIVE
 * formulas: line costs, subtotals, extra costs, tax, grand total, cost per
 * yield unit, and food-cost % are all Excel formulas over the raw inputs,
 * so users can trace — and tweak — the actual calculation.
 */
class RecipeCostExcelController extends RecipeCostPdfController
{
    private const MONEY  = '#,##0.00';
    private const MONEY4 = '#,##0.0000';
    private const QTY    = '0.####';
    private const PCT_RAW      = '0.##"%"'; // raw number (5 shown as 5%), used in ÷100 formulas
    private const PCT_FRACTION = '0.0%';    // true Excel fraction

    private const HEADER_FILL   = 'FFE5E7EB'; // gray-200
    private const CATEGORY_FILL = 'FF312E81'; // indigo-900
    private const TITLE_FILL    = 'FFEEF2FF'; // indigo-50
    private const TOTAL_FILL    = 'FFF3F4F6'; // gray-100

    public function all(Request $request)
    {
        return $this->generateWorkbook($request, isPrep: false);
    }

    public function prepAll(Request $request)
    {
        return $this->generateWorkbook($request, isPrep: true);
    }

    private function generateWorkbook(Request $request, bool $isPrep)
    {
        $query = Recipe::with([
            'lines.ingredient.baseUom', 'lines.ingredient.uomConversions', 'lines.ingredient.taxRate',
            'lines.uom', 'yieldUom', 'department',
            'prices.priceClass', 'outlets',
        ])->where('recipes.is_prep', $isPrep);

        $this->applyFilters($query, $request, $isPrep);
        $this->applyDashboardSort($query, $isPrep);
        $recipes = $this->applyCostFilter($query->get(), $request);

        $priceClasses = $isPrep ? collect() : RecipePriceClass::ordered()->get();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(Auth::user()->name)
            ->setTitle($isPrep ? 'Prep Item Costs' : 'Recipe Costs');

        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Summary');
        $details = $spreadsheet->createSheet();
        $details->setTitle('Details');

        // Details first: the summary references its cells.
        $refs = $this->buildDetailsSheet($details, $recipes, $isPrep);
        $this->buildSummarySheet($summary, $recipes, $priceClasses, $isPrep, $refs, $request);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = $this->safeFilename(
            ($isPrep ? 'prep-item-costs-' : 'recipe-costs-') . now()->format('Y-m-d') . '.xlsx'
        );

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * One block per recipe: raw line inputs + formula-driven totals.
     * Returns per-recipe cell refs used by the Summary sheet:
     * [recipe_id => [ing, pack, extraRange, tax, grand, cps]] (Details! cell refs, some null).
     */
    private function buildDetailsSheet(Worksheet $sheet, $recipes, bool $isPrep): array
    {
        foreach (['A' => 34, 'B' => 11, 'C' => 8, 'D' => 10, 'E' => 14, 'F' => 13, 'G' => 8, 'H' => 11] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $refs = [];
        $row = 1;
        $currentCategory = null;

        foreach ($recipes as $recipe) {
            $data = $this->buildRecipeData($recipe);
            $category = $recipe->category ?: 'Uncategorised';

            if ($category !== $currentCategory) {
                $currentCategory = $category;
                $sheet->mergeCells("A{$row}:H{$row}");
                $this->text($sheet, "A{$row}", $category);
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::CATEGORY_FILL]],
                ]);
                $row += 2;
            }

            // Title row
            $title = $recipe->name . ($recipe->code ? " ({$recipe->code})" : '');
            $this->text($sheet, "A{$row}", $title);
            if (! $recipe->is_active) {
                $this->text($sheet, "G{$row}", 'INACTIVE');
                $sheet->getStyle("G{$row}")->getFont()->setBold(true)->getColor()->setARGB('FFDC2626');
            }
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::TITLE_FILL]],
            ]);
            $row++;

            // Line-items header
            foreach (['A' => 'Ingredient', 'B' => 'Qty', 'C' => 'UOM', 'D' => 'Waste %', 'E' => 'Unit Cost', 'F' => 'Line Cost', 'G' => 'Tax %', 'H' => 'Tax'] as $col => $label) {
                $this->text($sheet, "{$col}{$row}", $label);
            }
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_FILL]],
            ]);
            $row++;

            $ingSubRef  = $this->writeLineSection($sheet, $row, $data['lineData'], 'Ingredient Subtotal');
            $packSubRef = count($data['packagingData'])
                ? $this->writeLineSection($sheet, $row, $data['packagingData'], 'Packaging Subtotal')
                : null;

            // Extra costs: percent rows reference the ingredient subtotal so the % is live.
            $extraFirst = $extraLast = null;
            foreach ($data['extraCosts'] as $ec) {
                $isPercent = ($ec['type'] ?? 'value') === 'percent';
                $label = ($ec['label'] ?? 'Extra Cost') . ($isPercent ? ' (% of ingredient subtotal)' : '');
                $this->text($sheet, "A{$row}", $label);
                if ($isPercent) {
                    $sheet->setCellValue("D{$row}", floatval($ec['amount'] ?? 0));
                    $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::PCT_RAW);
                    $sheet->setCellValue("F{$row}", "={$ingSubRef}*D{$row}/100");
                } else {
                    $sheet->setCellValue("F{$row}", floatval($ec['amount'] ?? 0));
                }
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
                $extraFirst ??= $row;
                $extraLast = $row;
                $row++;
            }
            $extraRange = $extraFirst !== null ? "SUM(F{$extraFirst}:F{$extraLast})" : null;

            // Tax (informational — matches the PDF: not part of Total Cost)
            $taxRef = null;
            if ($data['totalTaxAll'] > 0) {
                $this->text($sheet, "E{$row}", 'Tax (not incl. in Total Cost)');
                // Subtotal refs are F-column cells; the matching tax subtotals sit in H on the same rows.
                $taxFormula = '=H' . substr($ingSubRef, 1) . ($packSubRef ? '+H' . substr($packSubRef, 1) : '');
                $sheet->setCellValue("H{$row}", $taxFormula);
                $sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
                $sheet->getStyle("E{$row}:H{$row}")->getFont()->getColor()->setARGB('FF6B7280');
                $taxRef = "H{$row}";
                $row++;
            }

            // Total Cost = ingredients + packaging + extra costs
            $grandParts = [$ingSubRef];
            if ($packSubRef) $grandParts[] = $packSubRef;
            if ($extraRange) $grandParts[] = $extraRange;
            $this->text($sheet, "E{$row}", 'Total Cost');
            $sheet->setCellValue("F{$row}", '=' . implode('+', $grandParts));
            $sheet->getStyle("E{$row}:F{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::TOTAL_FILL]],
            ]);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
            $grandRef = "F{$row}";
            $row++;

            if ($taxRef) {
                $this->text($sheet, "E{$row}", 'Total Cost (incl. tax)');
                $sheet->setCellValue("F{$row}", "={$grandRef}+{$taxRef}");
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
                $row++;
            }

            // Yield + cost per yield unit
            $yieldAbbr = $recipe->yieldUom?->abbreviation ?: 'unit';
            $this->text($sheet, "E{$row}", 'Yield Quantity');
            $sheet->setCellValue("F{$row}", $data['yieldQty']);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::QTY);
            $this->text($sheet, "G{$row}", $yieldAbbr);
            $yieldRef = "F{$row}";
            $row++;

            $this->text($sheet, "E{$row}", "Cost per {$yieldAbbr}");
            $sheet->setCellValue("F{$row}", "={$grandRef}/{$yieldRef}");
            $sheet->getStyle("E{$row}:F{$row}")->getFont()->setBold(true);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::MONEY4);
            $cpsRef = "F{$row}";
            $row++;

            // Pricing analysis (recipes only)
            if (! $isPrep) {
                $priced = collect($data['pricingAnalysis'])->filter(fn ($p) => $p['selling_price'] > 0)->values();
                if ($data['legacyPrice'] > 0) {
                    $priced->push(['name' => 'Menu Price', 'selling_price' => $data['legacyPrice']]);
                }
                if ($priced->isNotEmpty()) {
                    $row++;
                    foreach (['A' => 'Price Class', 'B' => 'Selling Price', 'C' => 'Food Cost %', 'D' => 'Gross Profit'] as $col => $label) {
                        $this->text($sheet, "{$col}{$row}", $label);
                    }
                    $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 9],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_FILL]],
                    ]);
                    $row++;
                    foreach ($priced as $p) {
                        $this->text($sheet, "A{$row}", $p['name']);
                        $sheet->setCellValue("B{$row}", $p['selling_price']);
                        $sheet->setCellValue("C{$row}", "=IF(B{$row}>0,{$cpsRef}/B{$row},\"\")");
                        $sheet->setCellValue("D{$row}", "=B{$row}-{$cpsRef}");
                        $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
                        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode(self::PCT_FRACTION);
                        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
                        $row++;
                    }
                }
            }

            $refs[$recipe->id] = [
                'ing'        => "Details!{$ingSubRef}",
                'pack'       => $packSubRef ? "Details!{$packSubRef}" : null,
                'extraRange' => $extraRange ? str_replace('SUM(F', 'SUM(Details!F', $extraRange) : null,
                'tax'        => $taxRef ? "Details!{$taxRef}" : null,
                'grand'      => "Details!{$grandRef}",
                'cps'        => "Details!{$cpsRef}",
            ];

            $row += 2;
        }

        return $refs;
    }

    /**
     * Write a run of line rows + a subtotal row. Each Line Cost / Tax cell is a
     * formula over the row's raw Qty, Waste %, Unit Cost, and Tax % inputs.
     * Advances $row (by ref); returns the subtotal cell ref (e.g. "F12").
     */
    private function writeLineSection(Worksheet $sheet, int &$row, array $lines, string $subtotalLabel): string
    {
        $first = $row;
        foreach ($lines as $l) {
            $this->text($sheet, "A{$row}", $l['ingredient']);
            $sheet->setCellValue("B{$row}", $l['quantity']);
            $this->text($sheet, "C{$row}", $l['uom']);
            $sheet->setCellValue("D{$row}", $l['waste_percentage']);
            $sheet->setCellValue("E{$row}", $l['unit_cost']);
            $sheet->setCellValue("F{$row}", "=E{$row}*(1+D{$row}/100)*B{$row}");
            $sheet->setCellValue("G{$row}", $l['tax_pct'] ?? 0);
            $sheet->setCellValue("H{$row}", "=F{$row}*G{$row}/100");
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode(self::QTY);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode(self::PCT_RAW);
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode(self::MONEY4);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::MONEY4);
            $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode(self::PCT_RAW);
            $sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode(self::MONEY4);
            $row++;
        }

        $this->text($sheet, "E{$row}", $subtotalLabel);
        if ($first < $row) {
            $last = $row - 1;
            $sheet->setCellValue("F{$row}", "=SUM(F{$first}:F{$last})");
            $sheet->setCellValue("H{$row}", "=SUM(H{$first}:H{$last})");
        } else {
            $sheet->setCellValue("F{$row}", 0);
            $sheet->setCellValue("H{$row}", 0);
        }
        $sheet->getStyle("E{$row}:H{$row}")->getFont()->setBold(true);
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $subtotalRef = "F{$row}";
        $row++;

        return $subtotalRef;
    }

    /**
     * One row per recipe; cost cells reference the Details sheet so the
     * summary stays live when inputs are changed there.
     */
    private function buildSummarySheet(Worksheet $sheet, $recipes, $priceClasses, bool $isPrep, array $refs, Request $request): void
    {
        $company = Auth::user()->company;
        $brandName = $company?->brand_name ?: $company?->name;
        $label = $isPrep ? 'Prep Item' : 'Recipe';

        $this->text($sheet, 'A1', (string) $brandName);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $this->text($sheet, 'A2', "{$label} Cost Export");
        $sheet->getStyle('A2')->getFont()->setSize(11);
        $this->text($sheet, 'A3', 'Exported by ' . Auth::user()->name . ' on ' . now()->format('d M Y H:i'));
        $row = 4;
        $activeFilters = $this->describeActiveFilters($request);
        if (count($activeFilters)) {
            $this->text($sheet, "A{$row}", 'Filters: ' . implode(' · ', $activeFilters));
            $row++;
        }
        $this->text($sheet, "A{$row}", 'Total: ' . $recipes->count() . ' ' . strtolower($label) . '(s)');
        $sheet->getStyle('A3:A' . $row)->getFont()->setSize(9)->getColor()->setARGB('FF6B7280');
        $row += 2;

        $headers = ['Name', 'Code', 'Category', 'Yield', 'Ingredient Cost', 'Packaging', 'Extra Costs', 'Tax', 'Total Cost', 'Cost / Unit'];
        $priceColStart = count($headers) + 1;
        if (! $isPrep) {
            foreach ($priceClasses as $pc) {
                $headers[] = "{$pc->name} Price";
                $headers[] = "{$pc->name} FC %";
            }
            $headers[] = 'Menu Price';
            $headers[] = 'Menu FC %';
        }

        $headerRow = $row;
        foreach ($headers as $i => $h) {
            $this->text($sheet, Coordinate::stringFromColumnIndex($i + 1) . $headerRow, $h);
        }
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_FILL]],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->freezePane('A' . ($headerRow + 1));
        $row++;

        foreach ($recipes as $recipe) {
            $r = $refs[$recipe->id] ?? null;
            if (! $r) continue;

            $yieldQty = rtrim(rtrim(number_format((float) $recipe->yield_quantity, 2), '0'), '.');
            $this->text($sheet, "A{$row}", $recipe->name);
            $this->text($sheet, "B{$row}", (string) $recipe->code);
            $this->text($sheet, "C{$row}", $recipe->category ?: 'Uncategorised');
            $this->text($sheet, "D{$row}", trim($yieldQty . ' ' . ($recipe->yieldUom?->abbreviation ?? '')));
            $sheet->setCellValue("E{$row}", "={$r['ing']}");
            $sheet->setCellValue("F{$row}", $r['pack'] ? "={$r['pack']}" : 0);
            $sheet->setCellValue("G{$row}", $r['extraRange'] ? "={$r['extraRange']}" : 0);
            $sheet->setCellValue("H{$row}", $r['tax'] ? "={$r['tax']}" : 0);
            $sheet->setCellValue("I{$row}", "={$r['grand']}");
            $sheet->setCellValue("J{$row}", "={$r['cps']}");
            $sheet->getStyle("E{$row}:I{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
            $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode(self::MONEY4);
            $sheet->getStyle("I{$row}")->getFont()->setBold(true);

            if (! $isPrep) {
                $priceMap = $recipe->prices->keyBy('recipe_price_class_id');
                $col = $priceColStart;
                foreach ($priceClasses as $pc) {
                    $sp = (float) ($priceMap->get($pc->id)?->selling_price ?? 0);
                    $priceCell = Coordinate::stringFromColumnIndex($col) . $row;
                    $fcCell    = Coordinate::stringFromColumnIndex($col + 1) . $row;
                    $sheet->setCellValue($priceCell, $sp);
                    $sheet->setCellValue($fcCell, "=IF({$priceCell}>0,\$J{$row}/{$priceCell},\"\")");
                    $sheet->getStyle($priceCell)->getNumberFormat()->setFormatCode(self::MONEY);
                    $sheet->getStyle($fcCell)->getNumberFormat()->setFormatCode(self::PCT_FRACTION);
                    $col += 2;
                }
                $priceCell = Coordinate::stringFromColumnIndex($col) . $row;
                $fcCell    = Coordinate::stringFromColumnIndex($col + 1) . $row;
                $sheet->setCellValue($priceCell, (float) $recipe->selling_price);
                $sheet->setCellValue($fcCell, "=IF({$priceCell}>0,\$J{$row}/{$priceCell},\"\")");
                $sheet->getStyle($priceCell)->getNumberFormat()->setFormatCode(self::MONEY);
                $sheet->getStyle($fcCell)->getNumberFormat()->setFormatCode(self::PCT_FRACTION);
            }

            $row++;
        }

        foreach (['A' => 32, 'B' => 12, 'C' => 22, 'D' => 12, 'E' => 13, 'F' => 11, 'G' => 11, 'H' => 10, 'I' => 12, 'J' => 12] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        for ($i = 11; $i <= count($headers); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(11);
        }
    }

    /**
     * Write user-entered text as an explicit string so names starting with
     * "=", "+", or "-" can't be interpreted as formulas.
     */
    private function text(Worksheet $sheet, string $cell, ?string $value): void
    {
        $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
    }
}
