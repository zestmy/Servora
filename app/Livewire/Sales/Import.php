<?php

namespace App\Livewire\Sales;

use App\Models\Outlet;
use App\Models\SalesCategory;
use App\Models\SalesRecord;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class Import extends Component
{
    use WithFileUploads;

    public $file = null;

    public string $step = 'upload'; // upload | preview | done

    public array $rows           = [];
    public array $categoryNames  = [];
    public int   $totalRows      = 0;
    public int   $validRows      = 0;
    public int   $importedCount  = 0;
    public int   $skippedCount   = 0;

    protected function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
        ];
    }

    protected function messages(): array
    {
        return [
            'file.mimes' => 'Only CSV (.csv) and Excel (.xlsx) files are supported.',
            'file.max'   => 'File size must not exceed 10 MB.',
        ];
    }

    public function processUpload(): void
    {
        $this->validate();

        $path = $this->file->getRealPath();
        $ext  = strtolower($this->file->getClientOriginalExtension());

        try {
            $rawRows = ($ext === 'xlsx') ? $this->parseXlsx($path) : $this->parseCsv($path);
        } catch (\Throwable $e) {
            $this->addError('file', 'Could not parse file: ' . $e->getMessage());
            return;
        }

        if (empty($rawRows)) {
            $this->addError('file', 'The file appears to be empty or has no data rows after the header.');
            return;
        }

        // Check required headers
        $firstRow = $rawRows[0];
        if (! array_key_exists('date', $firstRow)) {
            $this->addError('file', 'Missing required "date" column.');
            return;
        }

        // Load sales categories for matching
        $categories = SalesCategory::active()->ordered()->get();
        $catByName = $categories->mapWithKeys(fn ($c) => [strtolower($c->name) => $c]);
        $this->categoryNames = $categories->pluck('name')->toArray();

        // Detect which category columns exist in the file
        $headers = array_keys($firstRow);
        $matchedCategories = [];
        foreach ($headers as $h) {
            $cat = $catByName[strtolower($h)] ?? null;
            if ($cat) {
                $matchedCategories[$h] = $cat;
            }
        }

        // Valid meal period values
        $validMealPeriods = ['all_day', 'breakfast', 'lunch', 'tea_time', 'dinner', 'supper'];
        $mealPeriodMap = [
            'all day' => 'all_day', 'allday' => 'all_day', 'all_day' => 'all_day',
            'breakfast' => 'breakfast', 'lunch' => 'lunch',
            'tea time' => 'tea_time', 'tea_time' => 'tea_time', 'teatime' => 'tea_time',
            'dinner' => 'dinner', 'supper' => 'supper',
        ];

        $this->rows = [];

        foreach ($rawRows as $i => $raw) {
            $rowNum    = $i + 2;
            $rowErrors = [];

            // Date
            $dateStr = trim($raw['date'] ?? '');
            $parsedDate = null;
            if (! $dateStr) {
                $rowErrors[] = 'Date is required';
            } else {
                try {
                    $parsedDate = \Carbon\Carbon::parse($dateStr)->format('Y-m-d');
                } catch (\Throwable $e) {
                    $rowErrors[] = 'Invalid date format "' . $dateStr . '"';
                }
            }

            // Meal period
            $mealRaw = strtolower(trim($raw['meal period'] ?? $raw['meal_period'] ?? 'all_day'));
            $mealPeriod = $mealPeriodMap[$mealRaw] ?? null;
            if (! $mealPeriod) {
                $mealPeriod = 'all_day';
            }

            // Pax
            $pax = trim($raw['pax'] ?? '0');
            if (! is_numeric($pax) || (int) $pax < 0) {
                $rowErrors[] = 'Pax must be a positive number';
                $pax = 0;
            }
            $pax = (int) $pax;

            // Reference
            $reference = trim($raw['reference'] ?? '');

            // Parse category revenues
            $categoryRevenues = [];
            $totalRevenue = 0;

            foreach ($matchedCategories as $headerName => $cat) {
                $value = trim($raw[$headerName] ?? '0');
                $value = str_replace(',', '', $value); // remove thousand separators
                if (! is_numeric($value)) {
                    $rowErrors[] = $cat->name . ' revenue must be a number';
                    $value = 0;
                }
                $revenue = max(0, (float) $value);
                $categoryRevenues[] = [
                    'sales_category_id'      => $cat->id,
                    'ingredient_category_id' => $cat->ingredient_category_id,
                    'name'                   => $cat->name,
                    'revenue'                => $revenue,
                ];
                $totalRevenue += $revenue;
            }

            // If no category columns matched, check for a "total revenue" or "revenue" column
            if (empty($matchedCategories)) {
                $rev = trim($raw['total revenue'] ?? $raw['revenue'] ?? '0');
                $rev = str_replace(',', '', $rev);
                if (is_numeric($rev)) {
                    $totalRevenue = max(0, (float) $rev);
                }
            }

            if ($totalRevenue <= 0 && empty($rowErrors)) {
                $rowErrors[] = 'Total revenue must be greater than 0';
            }

            $this->rows[] = [
                'row'                => $rowNum,
                'date'               => $parsedDate ?? $dateStr,
                'reference'          => $reference,
                'meal_period'        => $mealPeriod,
                'pax'                => $pax,
                'category_revenues'  => $categoryRevenues,
                'total_revenue'      => round($totalRevenue, 4),
                'errors'             => $rowErrors,
                'skip'               => ! empty($rowErrors),
            ];
        }

        $this->totalRows = count($this->rows);
        $this->validRows = collect($this->rows)->where('skip', false)->count();
        $this->step      = 'preview';
    }

    public function import(): void
    {
        $user      = Auth::user();
        $companyId = $user->company_id;
        $outletId  = $user->activeOutletId() ?: Outlet::where('company_id', $companyId)->value('id');
        $imported  = 0;
        $skipped   = 0;

        foreach ($this->rows as $row) {
            if ($row['skip']) {
                $skipped++;
                continue;
            }

            $record = SalesRecord::create([
                'company_id'       => $companyId,
                'outlet_id'        => $outletId,
                'sale_date'        => $row['date'],
                'meal_period'      => $row['meal_period'],
                'pax'              => $row['pax'],
                'reference_number' => $row['reference'] ?: null,
                'total_revenue'    => $row['total_revenue'],
                'total_cost'       => 0,
                'created_by'       => $user->id,
            ]);

            // Create lines per category
            foreach ($row['category_revenues'] as $catRev) {
                if ($catRev['revenue'] <= 0) continue;

                $record->lines()->create([
                    'sales_category_id'      => $catRev['sales_category_id'],
                    'ingredient_category_id' => $catRev['ingredient_category_id'],
                    'item_name'              => $catRev['name'],
                    'quantity'               => 1,
                    'unit_price'             => $catRev['revenue'],
                    'unit_cost'              => 0,
                    'total_revenue'          => round($catRev['revenue'], 4),
                    'total_cost'             => 0,
                ]);
            }

            $imported++;
        }

        $this->importedCount = $imported;
        $this->skippedCount  = $skipped;
        $this->step          = 'done';
    }

    public function restart(): void
    {
        $this->file          = null;
        $this->step          = 'upload';
        $this->rows          = [];
        $this->categoryNames = [];
        $this->totalRows     = 0;
        $this->validRows     = 0;
        $this->importedCount = 0;
        $this->skippedCount  = 0;
        $this->resetValidation();
    }

    public function downloadTemplate()
    {
        $categories = SalesCategory::active()->ordered()->get();
        $catNames = $categories->pluck('name')->toArray();

        $headers = array_merge(['date', 'reference', 'meal_period', 'pax'], $catNames, ['total_revenue']);

        $sampleRevenues = array_map(fn () => '1500.00', $catNames);
        $sampleTotal = number_format(1500 * count($catNames), 2, '.', '');

        $sample = [
            array_merge(['2026-03-10', 'INV-001', 'lunch', '50'], $sampleRevenues, [$sampleTotal]),
            array_merge(['2026-03-10', 'INV-002', 'dinner', '80'], $sampleRevenues, [$sampleTotal]),
            array_merge(['2026-03-11', '', 'all_day', '120'], $sampleRevenues, [$sampleTotal]),
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        foreach ($sample as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response()->streamDownload(
            fn () => print($csv),
            'sales_import_template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function render()
    {
        return view('livewire.sales.import')
            ->layout('layouts.app', ['title' => 'Import Sales']);
    }

    // ── Parsers ───────────────────────────────────────────────────────────────

    private function parseCsv(string $path): array
    {
        $reader = new CsvReader();
        $reader->open($path);

        $rows    = [];
        $headers = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(
                    fn ($c) => trim((string) $c->getValue()),
                    $row->getCells()
                );

                if ($headers === null) {
                    $headers = array_map('strtolower', array_map('trim', $cells));
                    continue;
                }

                if (array_filter($cells) === []) continue;

                $rows[] = array_combine(
                    $headers,
                    array_pad($cells, count($headers), '')
                );
            }
            break;
        }

        $reader->close();
        return $rows;
    }

    private function parseXlsx(string $path): array
    {
        $reader = new XlsxReader();
        $reader->open($path);

        $rows    = [];
        $headers = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(
                    fn ($c) => trim((string) $c->getValue()),
                    $row->getCells()
                );

                if ($headers === null) {
                    $headers = array_map('strtolower', array_map('trim', $cells));
                    continue;
                }

                if (array_filter($cells) === []) continue;

                $rows[] = array_combine(
                    $headers,
                    array_pad($cells, count($headers), '')
                );
            }
            break;
        }

        $reader->close();
        return $rows;
    }
}
