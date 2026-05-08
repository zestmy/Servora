<?php

namespace App\Livewire\Sales;

use App\Models\Outlet;
use App\Models\SalesCategory;
use App\Models\SalesRecord;
use App\Services\ZeoniqImportService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ZeoniqExcelImport extends Component
{
    use WithFileUploads;

    public bool   $showModal        = false;
    public string $step             = 'upload'; // upload | review
    public        $importFile       = null;
    public string $importError      = '';
    public bool   $importProcessing = false;

    // Detected report type: session_sales | daily_summary
    public string $reportType = '';

    // Parsed records for review
    // For session_sales: [{date, outlet_code, sessions: [{meal_period, transactions, gross_revenue, ...}]}]
    // For daily_summary: [{date, outlet_code, meal_period, gross_revenue, ...}]
    public array $parsedRecords = [];

    // Outlet mapping: outlet_code => selected_outlet_id
    public array $outletMapping = [];

    // Which records to include (by index)
    public array $includeRecords = [];

    // ── Open / Close ─────────────────────────────────────────────────────────

    public function mount(): void
    {
        if (request()->query('import') === 'zeoniq-excel') {
            $this->resetImport();
            $this->showModal = true;
        }
    }

    #[On('open-zeoniq-excel-import')]
    public function open(): void
    {
        $this->resetImport();
        $this->showModal = true;
    }

    public function close(): void
    {
        $this->resetImport();
        $this->showModal = false;
    }

    private function resetImport(): void
    {
        $this->step             = 'upload';
        $this->importFile       = null;
        $this->importError      = '';
        $this->importProcessing = false;
        $this->reportType       = '';
        $this->parsedRecords    = [];
        $this->outletMapping    = [];
        $this->includeRecords   = [];
    }

    // ── Process uploaded file ────────────────────────────────────────────────

    public function processFile(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        $this->importError      = '';
        $this->importProcessing = true;

        try {
            $service = new ZeoniqImportService();
            $path    = $this->importFile->getRealPath();

            // Detect report type
            $this->reportType = $service->detectReportType($path);

            if ($this->reportType === 'session_sales') {
                $this->parsedRecords = $service->parseSessionSalesExcel($path);
            } elseif ($this->reportType === 'daily_summary') {
                $this->parsedRecords = $service->parseDailySummaryExcel($path);
            } else {
                $this->importError = 'Could not detect Zeoniq report type. Please ensure this is a valid Session Sales Listing or Daily Summary export.';
                $this->importProcessing = false;
                return;
            }

            if (empty($this->parsedRecords)) {
                $this->importError = 'No data found in the uploaded file.';
                $this->importProcessing = false;
                return;
            }

            // Build outlet mapping
            $this->buildOutletMapping($service->extractOutlets($this->parsedRecords));

            // Initialize include flags for all records
            $this->includeRecords = array_fill(0, count($this->parsedRecords), true);

            $this->step = 'review';

        } catch (\Exception $e) {
            $this->importError = 'Error processing file: ' . $e->getMessage();
        }

        $this->importProcessing = false;
    }

    private function buildOutletMapping(array $outletCodes): void
    {
        $userOutlets = Outlet::where('company_id', Auth::user()->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $this->outletMapping = [];

        foreach ($outletCodes as $code) {
            // Try to match outlet by code or name
            $matched = $userOutlets->first(function ($outlet) use ($code) {
                $codeLower = strtolower($code);
                $nameLower = strtolower($outlet->name);

                // Extract outlet name from format like "W001-KLCC"
                $parts = explode('-', $code);
                $outletName = count($parts) > 1 ? trim($parts[1]) : $code;

                return str_contains($nameLower, strtolower($outletName))
                    || str_contains($codeLower, $nameLower)
                    || str_contains($nameLower, $codeLower);
            });

            $this->outletMapping[$code] = $matched?->id
                ?? session('active_outlet_id')
                ?? $userOutlets->first()?->id;
        }
    }

    // ── Computed properties ──────────────────────────────────────────────────

    public function getRecordCountProperty(): int
    {
        return count(array_filter($this->includeRecords));
    }

    public function getTotalRecordsToCreateProperty(): int
    {
        $count = 0;
        foreach ($this->parsedRecords as $idx => $record) {
            if (empty($this->includeRecords[$idx])) continue;

            if ($this->reportType === 'session_sales') {
                $count += count($record['sessions'] ?? []);
            } else {
                $count++;
            }
        }
        return $count;
    }

    // ── Save all records ─────────────────────────────────────────────────────

    public function saveAll(): void
    {
        $companyId = Auth::user()->company_id;
        $userId    = Auth::id();
        $created   = 0;
        $skipped   = 0;
        $errors    = [];

        foreach ($this->parsedRecords as $idx => $record) {
            if (empty($this->includeRecords[$idx])) continue;

            $outletCode = $record['outlet_code'] ?? '';
            $outletId   = $this->outletMapping[$outletCode]
                ?? session('active_outlet_id')
                ?? Outlet::where('company_id', $companyId)->value('id');

            if (! $outletId) {
                $errors[] = "No outlet found for {$outletCode}";
                continue;
            }

            if ($this->reportType === 'session_sales') {
                // Handle session sales (multiple sessions per date)
                $date = $record['date'];

                foreach ($record['sessions'] as $session) {
                    $result = $this->createSalesRecord(
                        $companyId,
                        $outletId,
                        $userId,
                        $date,
                        $session['meal_period'],
                        $session
                    );

                    if ($result === true) {
                        $created++;
                    } elseif ($result === 'duplicate') {
                        $skipped++;
                    } else {
                        $errors[] = $result;
                    }
                }
            } else {
                // Handle daily summary (single all_day record per date)
                $result = $this->createSalesRecord(
                    $companyId,
                    $outletId,
                    $userId,
                    $record['date'],
                    'all_day',
                    $record
                );

                if ($result === true) {
                    $created++;
                } elseif ($result === 'duplicate') {
                    $skipped++;
                } else {
                    $errors[] = $result;
                }
            }
        }

        // Build result message
        $messages = [];
        if ($created > 0) {
            $messages[] = "{$created} record(s) created";
        }
        if ($skipped > 0) {
            $messages[] = "{$skipped} skipped (duplicates)";
        }
        if (! empty($errors)) {
            $messages[] = count($errors) . " error(s)";
        }

        if ($created > 0) {
            session()->flash('success', 'Zeoniq import completed: ' . implode(', ', $messages) . '.');
        } elseif ($skipped > 0) {
            session()->flash('warning', 'No records created. ' . implode(', ', $messages) . '.');
        } else {
            $this->addError('import', 'Import failed: ' . implode('; ', $errors));
            return;
        }

        $this->showModal = false;
        $this->dispatch('zeoniq-import-saved');
    }

    private function createSalesRecord(
        int $companyId,
        int $outletId,
        int $userId,
        string $date,
        string $mealPeriod,
        array $data
    ): mixed {
        // Check for duplicates
        $existingPeriods = SalesRecord::where('outlet_id', $outletId)
            ->whereDate('sale_date', $date)
            ->pluck('meal_period');

        if ($existingPeriods->isNotEmpty()) {
            // If all_day exists, skip any import for this date
            if ($existingPeriods->contains('all_day')) {
                return 'duplicate';
            }
            // If trying to import all_day and any records exist, skip
            if ($mealPeriod === 'all_day') {
                return 'duplicate';
            }
            // If same meal period exists, skip
            if ($existingPeriods->contains($mealPeriod)) {
                return 'duplicate';
            }
        }

        // Determine revenue values
        // For session_sales: total_sales is the inclusive amount
        // For daily_summary: total_sales is the final amount
        $totalSales     = (float) ($data['total_sales']     ?? 0);
        $netSales       = (float) ($data['net_sales']       ?? $totalSales);
        $grossRevenue   = (float) ($data['gross_revenue']   ?? $totalSales);
        $discountAmount = (float) ($data['discount_amount'] ?? 0);
        $taxAmount      = (float) ($data['tax_amount']      ?? 0);
        $serviceCharges = (float) ($data['service_charges'] ?? 0);
        $roundingAmount = (float) ($data['rounding_amount'] ?? 0);
        $transactions   = (int)   ($data['transactions']    ?? 1);
        $pax            = (int)   ($data['pax']             ?? $transactions);

        // Use net_sales as total_revenue (consistent with existing pattern)
        $totalRevenue = $netSales > 0 ? $netSales : $totalSales;

        $record = SalesRecord::create([
            'company_id'       => $companyId,
            'outlet_id'        => $outletId,
            'sale_date'        => $date,
            'meal_period'      => $mealPeriod,
            'pax'              => max(1, $pax),
            'transactions'     => $transactions > 0 ? $transactions : null,
            'total_revenue'    => round($totalRevenue, 4),
            'gross_revenue'    => $grossRevenue > 0 ? round($grossRevenue, 4) : null,
            'discount_amount'  => $discountAmount > 0 ? round($discountAmount, 4) : null,
            'tax_amount'       => $taxAmount > 0 ? round($taxAmount, 4) : null,
            'service_charges'  => $serviceCharges > 0 ? round($serviceCharges, 4) : null,
            'rounding_amount'  => $roundingAmount != 0 ? round($roundingAmount, 4) : null,
            'total_cost'       => 0,
            'created_by'       => $userId,
        ]);

        // Create a single line for the record
        $record->lines()->create([
            'sales_category_id'      => null,
            'ingredient_category_id' => null,
            'item_name'              => ucfirst(str_replace('_', ' ', $mealPeriod)) . ' Sales',
            'quantity'               => 1,
            'unit_price'             => round($totalRevenue, 4),
            'unit_cost'              => 0,
            'total_revenue'          => round($totalRevenue, 4),
            'total_cost'             => 0,
        ]);

        return true;
    }

    // ── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $mealPeriodOptions = SalesRecord::mealPeriodOptions();

        return view('livewire.sales.zeoniq-excel-import', compact('outlets', 'mealPeriodOptions'));
    }
}
