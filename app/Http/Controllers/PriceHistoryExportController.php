<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * PDF and Excel exports of the Price History view (Price Alerts > Price
 * History). The query here is THE definition of that view — the Livewire
 * component delegates to query(), so screen and export always agree.
 */
class PriceHistoryExportController extends Controller
{
    /** Hard cap so a runaway date range cannot exhaust memory. */
    public const MAX_ROWS = 2000;

    /**
     * Every Market List price change in range: each ingredient_price_history
     * row diffed against the previous row for the same (ingredient,
     * supplier); unchanged-cost receives excluded.
     */
    public static function query(int $companyId, string $dateFrom = '', string $dateTo = '', string $search = '', string $direction = '')
    {
        $q = DB::table('ingredient_price_history as iph')
            ->join('ingredients as i', 'i.id', '=', 'iph.ingredient_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'iph.supplier_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'iph.uom_id')
            ->where('i.company_id', $companyId)
            ->selectRaw("iph.id, iph.ingredient_id, iph.cost, iph.effective_date, iph.source,
                i.name as ingredient_name, s.name as supplier_name, u.abbreviation as uom_abbr,
                (SELECT iph2.cost FROM ingredient_price_history iph2
                 WHERE iph2.ingredient_id = iph.ingredient_id
                   AND COALESCE(iph2.supplier_id, 0) = COALESCE(iph.supplier_id, 0)
                   AND (iph2.effective_date < iph.effective_date
                        OR (iph2.effective_date = iph.effective_date AND iph2.id < iph.id))
                 ORDER BY iph2.effective_date DESC, iph2.id DESC LIMIT 1) as prev_cost");

        if ($dateFrom !== '') {
            $q->where('iph.effective_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $q->where('iph.effective_date', '<=', $dateTo . ' 23:59:59');
        }
        if ($search !== '') {
            $s = '%' . $search . '%';
            $q->where(fn ($w) => $w->where('i.name', 'like', $s)->orWhere('s.name', 'like', $s));
        }

        $outer = DB::query()->fromSub($q, 't')
            ->whereNotNull('t.prev_cost')
            ->whereColumn('t.prev_cost', '!=', 't.cost');
        if ($direction === 'increase') {
            $outer->whereColumn('t.cost', '>', 't.prev_cost');
        } elseif ($direction === 'decrease') {
            $outer->whereColumn('t.cost', '<', 't.prev_cost');
        }

        return $outer->orderByDesc('t.effective_date')->orderByDesc('t.id');
    }

    /** Rows + filter labels + summary stats for the request's filters. */
    protected function fetch(Request $request): array
    {
        $dateFrom  = (string) $request->input('from', '');
        $dateTo    = (string) $request->input('to', '');
        $search    = trim((string) $request->input('search', ''));
        $direction = in_array($request->input('direction'), ['increase', 'decrease'], true)
            ? (string) $request->input('direction') : '';

        $rows = self::query(Auth::user()->company_id, $dateFrom, $dateTo, $search, $direction)
            ->limit(self::MAX_ROWS + 1)
            ->get()
            ->map(function ($r) {
                $r->change_amt = (float) $r->cost - (float) $r->prev_cost;
                $r->change_pct = (float) $r->prev_cost > 0
                    ? round(($r->change_amt / (float) $r->prev_cost) * 100, 1)
                    : null;
                return $r;
            });

        $truncated = $rows->count() > self::MAX_ROWS;
        $rows      = $rows->take(self::MAX_ROWS);

        $filters = [];
        if ($dateFrom || $dateTo) $filters[] = 'Period: ' . ($dateFrom ?: '…') . ' – ' . ($dateTo ?: '…');
        if ($search !== '')       $filters[] = 'Search: "' . $search . '"';
        if ($direction !== '')    $filters[] = ucfirst($direction) . 's only';

        $stats = [
            'total'     => $rows->count(),
            'increases' => $rows->where('change_amt', '>', 0)->count(),
            'decreases' => $rows->where('change_amt', '<', 0)->count(),
            'netAmount' => $rows->sum('change_amt'),
        ];

        return [$rows, $filters, $stats, $truncated];
    }

    public function pdf(Request $request)
    {
        [$rows, $filters, $stats, $truncated] = $this->fetch($request);

        $company    = Auth::user()->company;
        $brandName  = $company?->brand_name ?: $company?->name;
        $logoBase64 = $this->companyLogoBase64($company);

        $pdf = Pdf::loadView('pdf.price-history', compact(
            'rows', 'filters', 'stats', 'truncated', 'brandName', 'logoBase64'
        ))->setPaper('a4', 'landscape');

        return $pdf->stream('Price-History-' . now()->format('Y-m-d') . '.pdf');
    }

    public function excel(Request $request)
    {
        [$rows, $filters, $stats, $truncated] = $this->fetch($request);

        $company   = Auth::user()->company;
        $brandName = $company?->brand_name ?: $company?->name;

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator(Auth::user()->name)->setTitle('Price History');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Price History');

        $sheet->setCellValueExplicit('A1', $brandName . ' — Market List Price History', DataType::TYPE_STRING);
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEF2FF');
        $sheet->getRowDimension(1)->setRowHeight(24);

        $subtitle = 'Generated ' . now()->format('d M Y, h:i A')
            . ' · ' . $stats['total'] . ' change(s) — ' . $stats['increases'] . ' up / ' . $stats['decreases'] . ' down'
            . ' · Net RM ' . number_format($stats['netAmount'], 2)
            . ($truncated ? ' · TRUNCATED at ' . self::MAX_ROWS . ' rows — narrow the date range for the rest' : '');
        if (! empty($filters)) {
            $subtitle .= ' · ' . implode(' · ', $filters);
        }
        $sheet->setCellValueExplicit('A2', $subtitle, DataType::TYPE_STRING);
        $sheet->mergeCells('A2:I2');
        $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setARGB('FF6B7280');

        $headers = ['No.', 'Effective Date', 'Product', 'Unit', 'Supplier', 'Old Price', 'New Price', 'Change (RM)', 'Change %'];
        $headerRow = 4;
        foreach ($headers as $col => $h) {
            $cell = $sheet->getCell([$col + 1, $headerRow]);
            $cell->setValueExplicit($h, DataType::TYPE_STRING);
        }
        $sheet->getStyle("A{$headerRow}:I{$headerRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A{$headerRow}:I{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2D3748');

        $r = $headerRow + 1;
        $n = 0;
        foreach ($rows as $row) {
            $n++;
            $sheet->setCellValue([1, $r], $n);
            $sheet->setCellValueExplicit([2, $r], \Carbon\Carbon::parse($row->effective_date)->format('d M Y'), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([3, $r], $row->ingredient_name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([4, $r], (string) ($row->uom_abbr ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([5, $r], (string) ($row->supplier_name ?? 'Manual edit'), DataType::TYPE_STRING);
            $sheet->setCellValue([6, $r], (float) $row->prev_cost);
            $sheet->setCellValue([7, $r], (float) $row->cost);
            $sheet->setCellValue([8, $r], round($row->change_amt, 4));
            $sheet->setCellValue([9, $r], $row->change_pct !== null ? $row->change_pct / 100 : null);
            $r++;
        }

        $last = $r - 1;
        if ($last >= $headerRow + 1) {
            $sheet->getStyle("F5:H{$last}")->getNumberFormat()->setFormatCode('#,##0.0000');
            $sheet->getStyle("I5:I{$last}")->getNumberFormat()->setFormatCode('+0.0%;-0.0%');
        }
        $sheet->setAutoFilter("A{$headerRow}:I{$headerRow}");
        $sheet->freezePane('A' . ($headerRow + 1));
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'Price-History-' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    private function companyLogoBase64($company): ?string
    {
        if (! $company?->logo) return null;
        try {
            $path = Storage::disk('public')->path($company->logo);
            if (file_exists($path)) {
                $mime = mime_content_type($path);
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
        } catch (\Throwable $e) {
        }
        return null;
    }
}
