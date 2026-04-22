<?php

namespace App\Livewire\Sales;

use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\SalesCategory;
use App\Models\SalesRecord;
use App\Services\VisionService;
use Illuminate\Support\Facades\Auth;
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
    public bool   $includeAllDay       = true;
    public        $allDayPax           = 1;
    public        $allDayTransactions  = 1;
    public string $allDayReference     = '';
    // [{ingredient_category_id|null, sales_category_id|null, category_name,
    //   category_color, revenue, unmatched}]
    // Populated from departments[] when the POS provides an F&B breakdown,
    // otherwise a single "Net Sales" line so the all-day record has a body.
    public array  $allDayLines         = [];

    // Session entries — transactions come from the receipt; pax is pro-rated
    // from total_guests by each session's transaction share, editable.
    // [{meal_period, label, total_revenue, transactions, pax, include}]
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

    // ── Process uploaded image ────────────────────────────────────────────────

    public function processZReport(): void
    {
        $this->validate(['importFile' => 'required|file|mimes:jpg,jpeg,png,pdf|max:20480']);

        $this->importError      = '';
        $this->importProcessing = true;

        try {
            $vision = new VisionService();
            $data   = $vision->extractZReportData($this->importFile->getRealPath());

            $sessions    = $data['sessions'] ?? [];
            $departments = $data['departments'] ?? [];
            $summary     = $data['summary'] ?? [];

            if (empty($sessions) && empty($departments) && empty($summary['net_sales'])) {
                $this->importError      = 'Could not detect Z-report data. Ensure the image is clear and shows totals (Net Sales, Guests, Transactions).';
                $this->importProcessing = false;
                return;
            }

            $this->summary    = $summary;
            $this->importDate = $data['date'] ?? now()->toDateString();

            // All-day totals from the receipt
            $this->allDayPax          = (int) ($summary['total_guests'] ?? 0) ?: 1;
            $this->allDayTransactions = (int) ($summary['total_transactions'] ?? 0) ?: 1;

            $this->allDayLines = $this->buildAllDayLines($departments, $summary);
            $this->sessionEntries = $this->buildSessionEntries($sessions, $summary);

            $this->step = 'review';

        } catch (\RuntimeException $e) {
            $this->importError = $e->getMessage();
        }

        $this->importProcessing = false;
    }

    private function buildAllDayLines(array $departments, array $summary): array
    {
        // If the POS gave us an F&B department breakdown, match it against
        // IngredientCategory and SalesCategory. Otherwise emit a single
        // "Net Sales" line so the all-day record has at least one line.

        $categoryIndex     = IngredientCategory::roots()->active()->ordered()->get()
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
            if (! empty($lines)) {
                return $lines;
            }
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
        $totalGuests = (int) ($summary['total_guests'] ?? 0);

        $entries = [];
        foreach ($sessions as $s) {
            $label  = trim((string) ($s['label'] ?? ''));
            $amount = (float) ($s['amount'] ?? 0);
            if ($amount <= 0) continue;

            $transactions = isset($s['transactions']) && $s['transactions'] !== null
                ? (int) $s['transactions']
                : 0;

            // Pro-rate pax from total guests by this session's transaction share.
            // Editable in the UI so the user can correct it.
            $pax = 1;
            if ($totalGuests > 0 && $totalTrans > 0 && $transactions > 0) {
                $pax = max(1, (int) round($transactions / $totalTrans * $totalGuests));
            }

            $entries[] = [
                'meal_period'   => (string) ($s['meal_period'] ?? ''),
                'label'         => $label,
                'total_revenue' => (string) $amount,
                'transactions'  => $transactions > 0 ? $transactions : 1,
                'pax'           => $pax,
                'include'       => true,
            ];
        }

        return $entries;
    }

    // ── Save all records ──────────────────────────────────────────────────────

    public function saveAll(): void
    {
        $this->validate([
            'importDate'                     => 'required|date',
            'allDayPax'                      => 'required|integer|min:1',
            'allDayTransactions'             => 'required|integer|min:1',
            'allDayLines.*.revenue'          => 'required|numeric|min:0',
            'sessionEntries.*.total_revenue' => 'required|numeric|min:0',
            'sessionEntries.*.pax'           => 'required|integer|min:1',
            'sessionEntries.*.transactions'  => 'required|integer|min:1',
            'sessionEntries.*.meal_period'   => 'required|in:all_day,breakfast,lunch,tea_time,dinner,supper',
        ]);

        $outletId  = Outlet::where('company_id', Auth::user()->company_id)->value('id');
        $companyId = Auth::user()->company_id;
        $userId    = Auth::id();

        // All-day record — carries the Z-report-level totals (gross, discount, tax, etc.)
        if ($this->includeAllDay) {
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
        }

        // Session records — pax (guests) and transactions (bills) stored separately
        // so reports can compute Avg Guest Value and ATV per session.
        foreach ($this->sessionEntries as $entry) {
            if (empty($entry['include'])) continue;
            $rev = (float) $entry['total_revenue'];
            if ($rev <= 0) continue;

            $record = SalesRecord::create([
                'company_id'    => $companyId,
                'outlet_id'     => $outletId,
                'sale_date'     => $this->importDate,
                'meal_period'   => $entry['meal_period'],
                'pax'           => (int) $entry['pax'],
                'transactions'  => (int) $entry['transactions'],
                'total_revenue' => round($rev, 4),
                'total_cost'    => 0,
                'created_by'    => $userId,
            ]);

            $record->lines()->create([
                'ingredient_category_id' => null,
                'item_name'              => $entry['label'] . ' Session',
                'quantity'               => 1,
                'unit_price'             => $rev,
                'unit_cost'              => 0,
                'total_revenue'          => round($rev, 4),
                'total_cost'             => 0,
            ]);
        }

        session()->flash('success', 'Z-Report imported — ' .
            ($this->includeAllDay ? '1 All Day + ' : '') .
            collect($this->sessionEntries)->where('include', true)->count() . ' session record(s) created.');

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
