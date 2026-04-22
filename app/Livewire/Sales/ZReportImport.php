<?php

namespace App\Livewire\Sales;

use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\SalesCategory;
use App\Models\SalesRecord;
use App\Services\VisionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ZReportImport extends Component
{
    use WithFileUploads;

    public bool   $showModal        = false;
    public string $step             = 'upload'; // upload | review
    public        $importFile       = null;
    public string $importError      = '';
    public bool   $importProcessing = false;

    // Review — shared
    public string $importDate = '';

    // Full Z-report totals block — all fields surfaced from the receipt.
    // Keys: gross_amount, discount_incl_tax, net_sales, exclusive_tax, exclusive_charges,
    //       bill_rounding, total_sales, total_guests, total_transactions,
    //       avg_guest_value, atv_net, atv_gross
    public array $summary = [];

    // All Day entry (one record covering the whole day)
    // Only used when NO session entries are detected — sessions take priority
    // to avoid double-counting revenue for the day.
    public bool   $includeAllDay       = true;
    public        $allDayPax           = 1;
    public        $allDayTransactions  = 1;
    public string $allDayReference     = '';
    // [{ingredient_category_id|null, sales_category_id|null, category_name,
    //   category_color, revenue, unmatched}]
    public array  $allDayLines         = [];

    // Session entries — gross_amount is the inclusive amount from the receipt.
    // net_revenue is back-calculated at save time via proportional ratio.
    // [{meal_period, label, gross_amount, transactions, pax, include}]
    public array $sessionEntries = [];

    // ── Open / Close ─────────────────────────────────────────────────────────

    public function mount(): void
    {
        if (request()->query('scan') === 'zreport') {
            $this->resetImport();
            $this->showModal = true;
        }
    }

    #[On('open-z-import')]
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
        $this->step                = 'upload';
        $this->importFile          = null;
        $this->importError         = '';
        $this->importProcessing    = false;
        $this->importDate          = now()->toDateString();
        $this->summary             = [];
        $this->includeAllDay       = true;
        $this->allDayPax           = 1;
        $this->allDayTransactions  = 1;
        $this->allDayReference     = '';
        $this->allDayLines         = [];
        $this->sessionEntries      = [];
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    /** Whether sessions were extracted — when true, all-day is suppressed. */
    public function hasSessionEntries(): bool
    {
        return count($this->sessionEntries) > 0;
    }

    /**
     * Net-to-gross ratio derived from the Z-report summary.
     * Session gross amounts × this ratio = net revenue to store.
     * Falls back to 1.0 if summary data is incomplete.
     */
    private function netRatio(): float
    {
        $gross = (float) ($this->summary['gross_amount'] ?? 0);
        $net   = (float) ($this->summary['net_sales']    ?? 0);
        return ($gross > 0 && $net > 0) ? $net / $gross : 1.0;
    }

    /**
     * Proportionally distribute a Z-report summary total to a single session
     * based on that session's share of the day's gross revenue.
     */
    private function proportional(float $sessionGross, string $summaryKey): ?float
    {
        $gross = (float) ($this->summary['gross_amount'] ?? 0);
        $total = (float) ($this->summary[$summaryKey]   ?? 0);
        if ($gross <= 0 || $total <= 0) return null;
        return round($sessionGross * ($total / $gross), 4);
    }

    // ── Process uploaded image ────────────────────────────────────────────────

    public function processZReport(): void
    {
        $this->validate(['importFile' => 'required|file|mimes:jpg,jpeg,png,pdf|max:20480']);

        $this->importError      = '';
        $this->importProcessing = true;

        try {
            $vision = new VisionService();
            $data   = $vision->extractZReportData($this->importFile->getRealPath());

            $sessions    = $data['sessions']    ?? [];
            $departments = $data['departments'] ?? [];
            $summary     = $data['summary']     ?? [];

            if (empty($sessions) && empty($departments) && empty($summary['net_sales'])) {
                $this->importError      = 'Could not detect Z-report data. Ensure the image is clear and shows totals (Net Sales, Guests, Transactions).';
                $this->importProcessing = false;
                return;
            }

            $this->summary    = $summary;
            $this->importDate = $data['date'] ?? now()->toDateString();

            $this->allDayPax          = (int) ($summary['total_guests']       ?? 0) ?: 1;
            $this->allDayTransactions = (int) ($summary['total_transactions'] ?? 0) ?: 1;

            $this->allDayLines    = $this->buildAllDayLines($departments, $summary);
            $this->sessionEntries = $this->buildSessionEntries($sessions, $summary);

            // Sessions take priority — suppress all-day to avoid double-counting.
            $this->includeAllDay = count($this->sessionEntries) === 0;

            $this->step = 'review';

        } catch (\RuntimeException $e) {
            $this->importError = $e->getMessage();
        }

        $this->importProcessing = false;
    }

    private function buildAllDayLines(array $departments, array $summary): array
    {
        $categoryIndex      = IngredientCategory::roots()->active()->ordered()->get()
            ->keyBy(fn ($c) => strtolower(trim($c->name)));
        $salesCategoryIndex = SalesCategory::active()->ordered()->get()
            ->keyBy(fn ($c) => strtolower(trim($c->name)));

        if (! empty($departments)) {
            $lines = [];
            foreach ($departments as $dept) {
                $name   = trim((string) ($dept['name'] ?? ''));
                if ($name === '') continue;
                $amount = (float) ($dept['amount'] ?? 0);
                if ($amount <= 0) continue;

                $catKey   = strtolower($name);
                $category = $categoryIndex->get($catKey);
                $salesCat = $salesCategoryIndex->get($catKey);

                $lines[] = [
                    'ingredient_category_id' => $category?->id,
                    'sales_category_id'      => $salesCat?->id,
                    'category_name'          => $name,
                    'category_color'         => $category?->color ?? $salesCat?->color ?? '#6b7280',
                    'revenue'                => (string) $amount,
                    'unmatched'              => $category === null && $salesCat === null,
                ];
            }
            if (! empty($lines)) return $lines;
        }

        // Fallback — single Net Sales line
        return [[
            'ingredient_category_id' => null,
            'sales_category_id'      => null,
            'category_name'          => 'Net Sales',
            'category_color'         => '#6b7280',
            'revenue'                => (string) ((float) ($summary['net_sales'] ?? 0)),
            'unmatched'              => true,
        ]];
    }

    private function buildSessionEntries(array $sessions, array $summary): array
    {
        $totalTrans  = (int) ($summary['total_transactions'] ?? 0);
        $totalGuests = (int) ($summary['total_guests']       ?? 0);

        $entries = [];
        foreach ($sessions as $s) {
            $label  = trim((string) ($s['label'] ?? ''));
            $amount = (float) ($s['amount'] ?? 0);
            if ($amount <= 0) continue;

            $transactions = isset($s['transactions']) && $s['transactions'] !== null
                ? (int) $s['transactions']
                : 0;

            $pax = 1;
            if ($totalGuests > 0 && $totalTrans > 0 && $transactions > 0) {
                $pax = max(1, (int) round($transactions / $totalTrans * $totalGuests));
            }

            $entries[] = [
                'meal_period'  => (string) ($s['meal_period'] ?? ''),
                'label'        => $label,
                'gross_amount' => (string) $amount,   // inclusive amount from receipt
                'transactions' => $transactions > 0 ? $transactions : 1,
                'pax'          => $pax,
                'include'      => true,
            ];
        }

        return $entries;
    }

    // ── Save all records ──────────────────────────────────────────────────────

    public function saveAll(): void
    {
        $hasSessions = $this->hasSessionEntries();

        $rules = [
            'importDate'                      => 'required|date',
            'sessionEntries.*.gross_amount'   => 'required|numeric|min:0',
            'sessionEntries.*.pax'            => 'required|integer|min:1',
            'sessionEntries.*.transactions'   => 'required|integer|min:1',
            'sessionEntries.*.meal_period'    => 'required|in:all_day,breakfast,lunch,tea_time,dinner,supper',
        ];

        // Only validate all-day fields when sessions are absent
        if (! $hasSessions) {
            $rules['allDayPax']             = 'required|integer|min:1';
            $rules['allDayTransactions']    = 'required|integer|min:1';
            $rules['allDayLines.*.revenue'] = 'required|numeric|min:0';
        }

        $this->validate($rules);

        $outletId  = Outlet::where('company_id', Auth::user()->company_id)->value('id');
        $companyId = Auth::user()->company_id;
        $userId    = Auth::id();
        $netRatio  = $this->netRatio();

        // ── Session records (priority) ────────────────────────────────────────
        // When sessions are present, these replace the all-day record entirely.
        if ($hasSessions) {
            $includedCount = 0;
            foreach ($this->sessionEntries as $entry) {
                if (empty($entry['include'])) continue;

                $gross = (float) $entry['gross_amount'];
                if ($gross <= 0) continue;

                // Back-calculate net revenue by stripping tax and service charges
                // proportionally, using the day's total net-to-gross ratio.
                $net = round($gross * $netRatio, 4);

                $record = SalesRecord::create([
                    'company_id'       => $companyId,
                    'outlet_id'        => $outletId,
                    'sale_date'        => $this->importDate,
                    'meal_period'      => $entry['meal_period'],
                    'pax'              => (int) $entry['pax'],
                    'transactions'     => (int) $entry['transactions'],
                    'total_revenue'    => $net,
                    'gross_revenue'    => $gross,
                    'discount_amount'  => $this->proportional($gross, 'discount_incl_tax'),
                    'tax_amount'       => $this->proportional($gross, 'exclusive_tax'),
                    'service_charges'  => $this->proportional($gross, 'exclusive_charges'),
                    'rounding_amount'  => $this->proportional($gross, 'bill_rounding'),
                    'total_cost'       => 0,
                    'created_by'       => $userId,
                ]);

                $record->lines()->create([
                    'ingredient_category_id' => null,
                    'item_name'              => $entry['label'] . ' Session',
                    'quantity'               => 1,
                    'unit_price'             => $net,
                    'unit_cost'              => 0,
                    'total_revenue'          => $net,
                    'total_cost'             => 0,
                ]);

                $includedCount++;
            }

            session()->flash('success', "Z-Report imported — {$includedCount} session record(s) created.");

        // ── All Day record (fallback when no sessions) ────────────────────────
        } elseif ($this->includeAllDay) {
            $totalRevenue = collect($this->allDayLines)->sum(fn ($l) => (float) $l['revenue']);

            if ($totalRevenue > 0) {
                $record = SalesRecord::create([
                    'company_id'       => $companyId,
                    'outlet_id'        => $outletId,
                    'sale_date'        => $this->importDate,
                    'meal_period'      => 'all_day',
                    'pax'              => (int) $this->allDayPax,
                    'transactions'     => (int) $this->allDayTransactions,
                    'reference_number' => $this->allDayReference ?: null,
                    'total_revenue'    => round($totalRevenue, 4),
                    'gross_revenue'    => $this->summary['gross_amount']      ?? null,
                    'discount_amount'  => $this->summary['discount_incl_tax'] ?? null,
                    'tax_amount'       => $this->summary['exclusive_tax']     ?? null,
                    'service_charges'  => $this->summary['exclusive_charges'] ?? null,
                    'rounding_amount'  => $this->summary['bill_rounding']     ?? null,
                    'total_cost'       => 0,
                    'created_by'       => $userId,
                ]);

                foreach ($this->allDayLines as $line) {
                    $rev = (float) $line['revenue'];
                    if ($rev <= 0) continue;
                    $record->lines()->create([
                        'sales_category_id'      => $line['sales_category_id']      ?? null,
                        'ingredient_category_id' => $line['ingredient_category_id'] ?? null,
                        'item_name'              => $line['category_name'],
                        'quantity'               => 1,
                        'unit_price'             => $rev,
                        'unit_cost'              => 0,
                        'total_revenue'          => round($rev, 4),
                        'total_cost'             => 0,
                    ]);
                }
            }

            session()->flash('success', 'Z-Report imported — 1 All Day record created.');
        }

        $this->showModal = false;
        $this->dispatch('z-report-saved');
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        $mealPeriodOptions = SalesRecord::mealPeriodOptions();
        $categories        = IngredientCategory::roots()->active()->ordered()->get();

        return view('livewire.sales.z-report-import', compact('mealPeriodOptions', 'categories'));
    }
}
