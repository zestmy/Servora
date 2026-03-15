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

    public string $step = 'upload'; // upload | mapping | preview | done

    // Raw parsed data (kept between steps)
    public array $rawRows       = [];
    public array $fileHeaders   = [];

    // Column mapping: fileHeader => system field key
    // System fields: 'date', 'reference', 'meal_period', 'pax', 'ignore', 'cat:<id>'
    public array $columnMap = [];

    // Processed rows for preview/import
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

    // ── Step 1 → Step 2: Parse file and show mapping UI ───────────────────

    public function processUpload(): void
    {
        $this->validate();

        $path = $this->file->getRealPath();
        $ext  = strtolower($this->file->getClientOriginalExtension());

        try {
            $this->rawRows = ($ext === 'xlsx') ? $this->parseXlsx($path) : $this->parseCsv($path);
        } catch (\Throwable $e) {
            $this->addError('file', 'Could not parse file: ' . $e->getMessage());
            return;
        }

        if (empty($this->rawRows)) {
            $this->addError('file', 'The file appears to be empty or has no data rows after the header.');
            return;
        }

        $this->fileHeaders = array_keys($this->rawRows[0]);

        // Auto-map columns by best guess
        $categories = SalesCategory::active()->ordered()->get();
        $catByName = $categories->mapWithKeys(fn ($c) => [strtolower($c->name) => $c]);
        $this->categoryNames = $categories->pluck('name')->toArray();

        $systemFieldMap = [
            'date'          => 'date',
            'sale_date'     => 'date',
            'sale date'     => 'date',
            'reference'     => 'reference',
            'ref'           => 'reference',
            'reference_number' => 'reference',
            'invoice'       => 'reference',
            'meal_period'   => 'meal_period',
            'meal period'   => 'meal_period',
            'period'        => 'meal_period',
            'pax'           => 'pax',
            'covers'        => 'pax',
            'total_revenue' => 'ignore', // calculated, not imported
            'total revenue' => 'ignore',
            'total'         => 'ignore',
        ];

        $this->columnMap = [];
        foreach ($this->fileHeaders as $header) {
            $lower = strtolower($header);

            // Check system fields
            if (isset($systemFieldMap[$lower])) {
                $this->columnMap[$header] = $systemFieldMap[$lower];
                continue;
            }

            // Check sales categories
            $cat = $catByName[$lower] ?? null;
            if ($cat) {
                $this->columnMap[$header] = 'cat:' . $cat->id;
                continue;
            }

            // Default: ignore
            $this->columnMap[$header] = 'ignore';
        }

        $this->step = 'mapping';
    }

    // ── Step 2 → Step 3: Apply mapping and build preview ──────────────────

    public function applyMapping(): void
    {
        // Validate at least date is mapped
        $mappedFields = array_values($this->columnMap);
        if (! in_array('date', $mappedFields)) {
            $this->addError('mapping', 'You must map at least one column to "Date".');
            return;
        }

        // Check at least one category is mapped
        $hasCat = collect($mappedFields)->contains(fn ($v) => str_starts_with($v, 'cat:'));
        if (! $hasCat) {
            $this->addError('mapping', 'You must map at least one column to a sales category.');
            return;
        }

        // Load categories for mapped IDs
        $categories = SalesCategory::active()->ordered()->get()->keyBy('id');

        $mealPeriodMap = [
            'all day' => 'all_day', 'allday' => 'all_day', 'all_day' => 'all_day',
            'breakfast' => 'breakfast', 'lunch' => 'lunch',
            'tea time' => 'tea_time', 'tea_time' => 'tea_time', 'teatime' => 'tea_time',
            'dinner' => 'dinner', 'supper' => 'supper',
        ];

        // Build reverse map: system field => file header(s)
        $fieldToHeaders = [];
        foreach ($this->columnMap as $header => $field) {
            $fieldToHeaders[$field][] = $header;
        }

        // Check if meal_period is mapped
        $hasMealPeriod = isset($fieldToHeaders['meal_period']);

        $this->rows = [];
        $this->categoryNames = [];

        // Pre-load existing records for duplicate detection
        $user     = Auth::user();
        $outletId = $user->activeOutletId() ?: Outlet::where('company_id', $user->company_id)->value('id');
        $existingRecords = SalesRecord::where('outlet_id', $outletId)
            ->select('sale_date', 'meal_period')
            ->get()
            ->groupBy(fn ($r) => $r->sale_date->format('Y-m-d'));

        // Track within-batch entries for intra-file duplicate detection
        $batchSeen = []; // ['2026-03-10:lunch' => true, ...]

        // Collect mapped category names in order
        $mappedCatIds = [];
        foreach ($this->columnMap as $field) {
            if (str_starts_with($field, 'cat:')) {
                $catId = (int) substr($field, 4);
                if (! in_array($catId, $mappedCatIds)) {
                    $mappedCatIds[] = $catId;
                }
            }
        }
        foreach ($mappedCatIds as $catId) {
            $cat = $categories->get($catId);
            if ($cat) {
                $this->categoryNames[] = $cat->name;
            }
        }

        foreach ($this->rawRows as $i => $raw) {
            $rowNum    = $i + 2;
            $rowErrors = [];

            // Extract date
            $dateStr = $this->getFieldValue($raw, $fieldToHeaders, 'date');
            $parsedDate = null;
            if (! $dateStr) {
                $rowErrors[] = 'Date is required';
            } else {
                try {
                    $parsedDate = \Carbon\Carbon::parse($dateStr)->format('Y-m-d');
                } catch (\Throwable $e) {
                    $rowErrors[] = 'Invalid date "' . $dateStr . '"';
                }
            }

            // Meal period
            if ($hasMealPeriod) {
                $mealRaw = strtolower(trim($this->getFieldValue($raw, $fieldToHeaders, 'meal_period')));
                $mealPeriod = $mealPeriodMap[$mealRaw] ?? 'all_day';
            } else {
                $mealPeriod = 'all_day';
            }

            // Pax
            $paxStr = $this->getFieldValue($raw, $fieldToHeaders, 'pax');
            $pax = 0;
            if ($paxStr !== '') {
                if (! is_numeric($paxStr) || (int) $paxStr < 0) {
                    $rowErrors[] = 'Pax must be a positive number';
                } else {
                    $pax = (int) $paxStr;
                }
            }

            // Reference
            $reference = $this->getFieldValue($raw, $fieldToHeaders, 'reference');

            // Category revenues
            $categoryRevenues = [];
            $totalRevenue = 0;

            foreach ($this->columnMap as $header => $field) {
                if (! str_starts_with($field, 'cat:')) continue;

                $catId = (int) substr($field, 4);
                $cat = $categories->get($catId);
                if (! $cat) continue;

                $value = trim($raw[$header] ?? '0');
                $value = str_replace([',', ' '], '', $value);
                if ($value === '' || $value === '-') {
                    $value = '0';
                }
                if (! is_numeric($value)) {
                    $rowErrors[] = $cat->name . ' must be a number';
                    $value = 0;
                }
                $revenue = max(0, (float) $value);

                // If same category mapped from multiple columns, sum them
                $existingIdx = null;
                foreach ($categoryRevenues as $idx => $cr) {
                    if ($cr['sales_category_id'] === $catId) {
                        $existingIdx = $idx;
                        break;
                    }
                }

                if ($existingIdx !== null) {
                    $categoryRevenues[$existingIdx]['revenue'] += $revenue;
                } else {
                    $categoryRevenues[] = [
                        'sales_category_id'      => $cat->id,
                        'ingredient_category_id' => null,
                        'name'                   => $cat->name,
                        'revenue'                => $revenue,
                    ];
                }
                $totalRevenue += $revenue;
            }

            if ($totalRevenue <= 0 && empty($rowErrors)) {
                $rowErrors[] = 'Total revenue must be greater than 0';
            }

            // Duplicate detection (existing DB records + within this file)
            if ($parsedDate && empty($rowErrors)) {
                $batchKey   = $parsedDate . ':' . $mealPeriod;
                $dayRecords = $existingRecords[$parsedDate] ?? collect();

                // Check against existing DB records
                if ($dayRecords->isNotEmpty()) {
                    $hasAllDay = $dayRecords->contains('meal_period', 'all_day');

                    if ($hasAllDay) {
                        $rowErrors[] = 'An All Day record already exists for this date — skipped';
                    } elseif ($mealPeriod === 'all_day') {
                        $rowErrors[] = 'Meal period records already exist for this date — cannot add All Day';
                    } elseif ($dayRecords->contains('meal_period', $mealPeriod)) {
                        $periodLabel = ucfirst(str_replace('_', ' ', $mealPeriod));
                        $rowErrors[] = "{$periodLabel} record already exists for this date — skipped";
                    }
                }

                // Check within-batch duplicates
                if (empty($rowErrors)) {
                    if (isset($batchSeen[$batchKey])) {
                        $periodLabel = ucfirst(str_replace('_', ' ', $mealPeriod));
                        $rowErrors[] = "Duplicate {$periodLabel} for this date in the file — skipped";
                    } elseif ($mealPeriod === 'all_day' && collect($batchSeen)->keys()->contains(fn ($k) => str_starts_with($k, $parsedDate . ':'))) {
                        $rowErrors[] = 'Other meal periods for this date exist earlier in the file — cannot add All Day';
                    } elseif ($mealPeriod !== 'all_day' && isset($batchSeen[$parsedDate . ':all_day'])) {
                        $rowErrors[] = 'An All Day entry for this date exists earlier in the file — skipped';
                    } else {
                        $batchSeen[$batchKey] = true;
                    }
                }
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

    public function backToMapping(): void
    {
        $this->step = 'mapping';
        $this->rows = [];
        $this->resetErrorBag();
    }

    // ── Step 3 → Step 4: Import ───────────────────────────────────────────

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

            // Safety: check for duplicates at import time
            $dayRecords = SalesRecord::where('outlet_id', $outletId)
                ->where('sale_date', $row['date'])
                ->pluck('meal_period');

            if ($dayRecords->contains('all_day')
                || ($row['meal_period'] === 'all_day' && $dayRecords->isNotEmpty())
                || $dayRecords->contains($row['meal_period'])) {
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
        $this->rawRows       = [];
        $this->fileHeaders   = [];
        $this->columnMap     = [];
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

    /**
     * Get available mapping options for the dropdown.
     */
    public function getMappingOptionsProperty(): array
    {
        $options = [
            'ignore'      => '— Ignore this column —',
            'date'        => 'Date',
            'reference'   => 'Reference / Invoice #',
            'meal_period' => 'Meal Period',
            'pax'         => 'Pax / Covers',
        ];

        $categories = SalesCategory::active()->ordered()->get();
        foreach ($categories as $cat) {
            $options['cat:' . $cat->id] = 'Revenue: ' . $cat->name;
        }

        return $options;
    }

    public function render()
    {
        return view('livewire.sales.import')
            ->layout('layouts.app', ['title' => 'Import Sales']);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function getFieldValue(array $raw, array $fieldToHeaders, string $field): string
    {
        $headers = $fieldToHeaders[$field] ?? [];
        foreach ($headers as $h) {
            $val = trim($raw[$h] ?? '');
            if ($val !== '') return $val;
        }
        return '';
    }

    // ── Parsers ───────────────────────────────────────────────────────────

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
