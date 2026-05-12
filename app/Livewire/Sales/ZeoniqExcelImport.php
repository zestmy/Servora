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
    public string $step             = 'upload'; // upload | mapping | review
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

    // Department mapping
    public array $departmentNames = [];           // Unique Zeoniq departments found
    public array $departmentMapping = [];         // dept_name => sales_category_id
    public array $departmentCategoryNames = [];  // dept_name => sales_category_name (for display)
    public array $mergedDepartments = [];        // categoryId => ['name' => name, 'revenue' => total] (merged for display)
    public array $aiSuggestions = [];            // AI-suggested matches with confidence
    public bool  $aiSuggestionsLoaded = false;
    public bool  $aiSuggestionsError = false;
    public string $aiErrorMessage = '';
    public array $unmatchedDepartments = [];     // Departments without valid mapping
    public array $validationWarnings = [];       // Department total variance warnings

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
        $this->step                    = 'upload';
        $this->importFile              = null;
        $this->importError             = '';
        $this->importProcessing        = false;
        $this->reportType              = '';
        $this->parsedRecords           = [];
        $this->outletMapping           = [];
        $this->includeRecords          = [];
        $this->departmentNames         = [];
        $this->departmentMapping       = [];
        $this->departmentCategoryNames = [];
        $this->mergedDepartments       = [];
        $this->aiSuggestions           = [];
        $this->aiSuggestionsLoaded     = false;
        $this->aiSuggestionsError      = false;
        $this->aiErrorMessage          = '';
        $this->unmatchedDepartments    = [];
        $this->validationWarnings      = [];
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

            // Extract department names
            $this->departmentNames = $service->extractDepartmentNames($this->parsedRecords);

            // Validate department totals
            $this->validationWarnings = $service->validateDepartmentTotals($this->parsedRecords);

            // Check if we have department data
            if (!empty($this->departmentNames)) {
                // Load existing mappings from database
                $this->loadStoredMappings();

                // Always show mapping step when departments are found
                // This allows users to review/change existing mappings
                if ($this->hasUnmappedDepartments()) {
                    // Load AI suggestions for unmapped departments
                    $this->loadAiSuggestions();
                }
                $this->step = 'mapping';
            } else {
                // No departments detected, proceed as normal
                $this->buildOutletMapping($service->extractOutlets($this->parsedRecords));
                $this->includeRecords = array_fill(0, count($this->parsedRecords), true);
                $this->step = 'review';
            }

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

    // ── Department mapping methods ──────────────────────────────────────────

    private function loadStoredMappings(): void
    {
        $companyId = Auth::user()->company_id;
        $stored = \App\Models\ZeoniqDepartmentMapping::where('company_id', $companyId)
            ->whereIn('zeoniq_department_name', $this->departmentNames)
            ->get()
            ->keyBy('zeoniq_department_name');

        $this->departmentMapping = [];
        foreach ($this->departmentNames as $dept) {
            $mapping = $stored->get($dept);
            $this->departmentMapping[$dept] = $mapping?->sales_category_id;
        }
    }

    private function hasUnmappedDepartments(): bool
    {
        foreach ($this->departmentMapping as $categoryId) {
            if ($categoryId === null) {
                return true;
            }
        }
        return false;
    }

    private function loadAiSuggestions(): void
    {
        try {
            $matchingService = new \App\Services\ZeoniqDepartmentMatchingService();
            $categories = SalesCategory::active()->ordered()->get();

            $unmappedDepts = [];
            foreach ($this->departmentNames as $dept) {
                if (!$this->departmentMapping[$dept]) {
                    $unmappedDepts[] = $dept;
                }
            }

            if (!empty($unmappedDepts)) {
                $this->aiSuggestions = $matchingService->suggestMatches(
                    $unmappedDepts,
                    $categories
                );
                $this->aiSuggestionsLoaded = true;

                // Auto-apply high and medium confidence suggestions to dropdowns
                foreach ($this->aiSuggestions as $suggestion) {
                    $dept = $suggestion['zeoniq_department'];
                    $categoryId = $suggestion['suggested_category_id'];
                    $confidence = $suggestion['confidence'] ?? 'low';

                    // Auto-select high and medium confidence matches
                    if ($categoryId && in_array($confidence, ['high', 'medium'])) {
                        $this->departmentMapping[$dept] = $categoryId;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->aiSuggestionsError = true;
            $this->aiErrorMessage = 'AI suggestions unavailable: ' . $e->getMessage();
            \Log::warning('Zeoniq AI matching failed', ['error' => $e->getMessage()]);
        }
    }

    public function applyAiSuggestion(string $department): void
    {
        $suggestion = collect($this->aiSuggestions)
            ->firstWhere('zeoniq_department', $department);

        if ($suggestion && $suggestion['suggested_category_id']) {
            $this->departmentMapping[$department] = $suggestion['suggested_category_id'];
        }
    }

    public function applyAllAiSuggestions(): void
    {
        foreach ($this->aiSuggestions as $suggestion) {
            if ($suggestion['confidence'] === 'high' && $suggestion['suggested_category_id']) {
                $this->departmentMapping[$suggestion['zeoniq_department']]
                    = $suggestion['suggested_category_id'];
            }
        }
    }

    public function clearAllMappings(): void
    {
        // Reset all mappings to null
        foreach ($this->departmentNames as $dept) {
            $this->departmentMapping[$dept] = null;
        }

        // Reload AI suggestions for all departments
        $this->aiSuggestions = [];
        $this->aiSuggestionsLoaded = false;
        $this->aiSuggestionsError = false;
        $this->loadAiSuggestions();
    }

    public function proceedToReview(): void
    {
        // Validate all departments mapped
        $this->unmatchedDepartments = [];
        foreach ($this->departmentNames as $dept) {
            if (!$this->departmentMapping[$dept]) {
                $this->unmatchedDepartments[] = $dept;
            }
        }

        if (!empty($this->unmatchedDepartments)) {
            $this->addError('mapping', 'All departments must be mapped to a Sales Category. Missing: '
                . implode(', ', $this->unmatchedDepartments));
            return;
        }

        // Save mappings for future use
        $this->saveMappings();

        // Build department category names mapping for display
        $categoryIds = array_filter(array_values($this->departmentMapping));
        $categoryNames = SalesCategory::whereIn('id', $categoryIds)->pluck('name', 'id')->toArray();
        $this->departmentCategoryNames = [];
        foreach ($this->departmentMapping as $deptName => $categoryId) {
            $this->departmentCategoryNames[$deptName] = $categoryNames[$categoryId] ?? $deptName;
        }

        // Build outlet mapping and proceed
        $service = new ZeoniqImportService();
        $this->buildOutletMapping($service->extractOutlets($this->parsedRecords));
        $this->includeRecords = array_fill(0, count($this->parsedRecords), true);
        $this->step = 'review';
    }

    /**
     * Merge departments by their mapped Sales Category for display.
     * Multiple Excel departments mapped to the same category are combined.
     */
    public function getMergedDepartments(array $departments): array
    {
        $merged = [];

        foreach ($departments as $deptName => $deptRevenue) {
            $categoryId = $this->departmentMapping[$deptName] ?? null;
            $categoryName = $this->departmentCategoryNames[$deptName] ?? $deptName;

            if ($categoryId && $deptRevenue > 0) {
                if (!isset($merged[$categoryId])) {
                    $merged[$categoryId] = [
                        'name' => $categoryName,
                        'revenue' => 0,
                    ];
                }
                $merged[$categoryId]['revenue'] += $deptRevenue;
            }
        }

        return $merged;
    }

    private function saveMappings(): void
    {
        $companyId = Auth::user()->company_id;
        $userId = Auth::id();

        foreach ($this->departmentMapping as $dept => $categoryId) {
            if ($categoryId) {
                \App\Models\ZeoniqDepartmentMapping::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'zeoniq_department_name' => $dept,
                    ],
                    [
                        'sales_category_id' => $categoryId,
                        'created_by' => $userId,
                    ]
                );
            }
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

        // Create lines for departments if available
        $departments = $data['departments'] ?? [];

        if (!empty($departments)) {
            // Load Sales Category names for mapping
            $categoryNames = SalesCategory::whereIn('id', array_filter(array_values($this->departmentMapping)))
                ->pluck('name', 'id')
                ->toArray();

            // Merge departments that map to the same Sales Category
            $mergedByCategory = [];
            foreach ($departments as $deptName => $deptRevenue) {
                $categoryId = $this->departmentMapping[$deptName] ?? null;

                if ($categoryId && $deptRevenue > 0) {
                    if (!isset($mergedByCategory[$categoryId])) {
                        $mergedByCategory[$categoryId] = 0;
                    }
                    $mergedByCategory[$categoryId] += $deptRevenue;
                }
            }

            // Create one line per Sales Category with merged revenue
            foreach ($mergedByCategory as $categoryId => $totalRevenue) {
                $categoryName = $categoryNames[$categoryId] ?? 'Unknown';

                $record->lines()->create([
                    'sales_category_id'      => $categoryId,
                    'ingredient_category_id' => null,
                    'item_name'              => $categoryName,
                    'quantity'               => 1,
                    'unit_price'             => round($totalRevenue, 4),
                    'unit_cost'              => 0,
                    'total_revenue'          => round($totalRevenue, 4),
                    'total_cost'             => 0,
                ]);
            }
        } else {
            // Fallback: Create single line (existing behavior)
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
        }

        return true;
    }

    // ── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $salesCategories = SalesCategory::active()->ordered()->get();

        $mealPeriodOptions = SalesRecord::mealPeriodOptions();

        return view('livewire.sales.zeoniq-excel-import', compact('outlets', 'salesCategories', 'mealPeriodOptions'));
    }
}
