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

    // All Day entry (dept breakdown)
    public bool   $includeAllDay      = true;
    public        $allDayPax          = 1;
    public string $allDayReference    = '';
    // [{ingredient_category_id|null, category_name, category_color, revenue, unmatched}]
    public array  $allDayLines        = [];

    // Session entries
    // [{meal_period, label, total_revenue, pax, include}]
    public array $sessionEntries = [];

    // ── Open / Close ─────────────────────────────────────────────────────────

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
        $this->step             = 'upload';
        $this->importFile       = null;
        $this->importError      = '';
        $this->importProcessing = false;
        $this->importDate       = now()->toDateString();
        $this->includeAllDay    = true;
        $this->allDayPax        = 1;
        $this->allDayReference  = '';
        $this->allDayLines      = [];
        $this->sessionEntries   = [];
    }

    // ── Process uploaded image ────────────────────────────────────────────────

    public function processZReport(): void
    {
        $this->validate(['importFile' => 'required|file|mimes:jpg,jpeg,png,pdf|max:20480']);

        $this->importError      = '';
        $this->importProcessing = true;

        try {
            $vision = new VisionService();
            $text   = $vision->extractText($this->importFile->getRealPath());
            $parsed = $vision->parseZReport($text);

            if (empty($parsed['departments']) && empty($parsed['sessions'])) {
                $this->importError      = 'Could not detect Z-report data. Ensure the image is clear and shows the Department Sales and Session Report sections.';
                $this->importProcessing = false;
                return;
            }

            $this->importDate  = $parsed['date'] ?? now()->toDateString();
            $this->allDayPax   = $parsed['total_bills'] ?? 1;

            // Match dept names against active IngredientCategory roots (case-insensitive)
            $categories = IngredientCategory::roots()->active()->revenue()->ordered()->get()
                ->keyBy(fn ($c) => strtolower(trim($c->name)));

            // Also match against SalesCategory for the sales_category_id link
            $salesCategories = SalesCategory::active()->ordered()->get()
                ->keyBy(fn ($c) => strtolower(trim($c->name)));

            $this->allDayLines = [];
            foreach ($parsed['departments'] as $dept) {
                $catKey   = strtolower(trim($dept['name']));
                $category = $categories->get($catKey);
                $salesCat = $salesCategories->get($catKey);

                $this->allDayLines[] = [
                    'ingredient_category_id' => $category?->id,
                    'sales_category_id'      => $salesCat?->id,
                    'category_name'          => $dept['name'],
                    'category_color'         => $category?->color ?? $salesCat?->color ?? '#6b7280',
                    'revenue'                => (string) $dept['amount'],
                    'unmatched'              => $category === null && $salesCat === null,
                ];
            }

            // Build session entries
            $this->sessionEntries = [];
            foreach ($parsed['sessions'] as $session) {
                $this->sessionEntries[] = [
                    'meal_period'   => $session['meal_period'] ?? '',
                    'label'         => $session['label'],
                    'total_revenue' => (string) $session['amount'],
                    'pax'           => $session['bill_count'] ?? 1,
                    'include'       => true,
                ];
            }

            $this->step = 'review';

        } catch (\RuntimeException $e) {
            $this->importError = $e->getMessage();
        }

        $this->importProcessing = false;
    }

    // ── Save all records ──────────────────────────────────────────────────────

    public function saveAll(): void
    {
        $this->validate([
            'importDate'                     => 'required|date',
            'allDayPax'                      => 'required|integer|min:1',
            'allDayLines.*.revenue'          => 'required|numeric|min:0',
            'sessionEntries.*.total_revenue' => 'required|numeric|min:0',
            'sessionEntries.*.pax'           => 'required|integer|min:1',
            'sessionEntries.*.meal_period'   => 'required|in:all_day,breakfast,lunch,tea_time,dinner,supper',
        ]);

        $outletId  = Outlet::where('company_id', Auth::user()->company_id)->value('id');
        $companyId = Auth::user()->company_id;
        $userId    = Auth::id();

        // Save All Day entry (dept breakdown)
        if ($this->includeAllDay) {
            $totalRevenue = collect($this->allDayLines)->sum(fn ($l) => floatval($l['revenue']));
            if ($totalRevenue > 0) {
                $record = SalesRecord::create([
                    'company_id'       => $companyId,
                    'outlet_id'        => $outletId,
                    'sale_date'        => $this->importDate,
                    'meal_period'      => 'all_day',
                    'pax'              => (int) $this->allDayPax,
                    'reference_number' => $this->allDayReference ?: null,
                    'total_revenue'    => round($totalRevenue, 4),
                    'total_cost'       => 0,
                    'created_by'       => $userId,
                ]);

                foreach ($this->allDayLines as $line) {
                    $rev = floatval($line['revenue']);
                    if ($rev <= 0) continue;
                    $record->lines()->create([
                        'sales_category_id'      => $line['sales_category_id'] ?? null,
                        'ingredient_category_id' => $line['ingredient_category_id'] ?: null,
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

        // Save session entries
        foreach ($this->sessionEntries as $entry) {
            if (empty($entry['include'])) continue;
            $rev = floatval($entry['total_revenue']);
            if ($rev <= 0) continue;

            $record = SalesRecord::create([
                'company_id'    => $companyId,
                'outlet_id'     => $outletId,
                'sale_date'     => $this->importDate,
                'meal_period'   => $entry['meal_period'],
                'pax'           => (int) $entry['pax'],
                'total_revenue' => round($rev, 4),
                'total_cost'    => 0,
                'created_by'    => $userId,
            ]);

            // Session entries have no dept breakdown — one uncategorised line
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
        $categories        = IngredientCategory::roots()->active()->revenue()->ordered()->get();

        return view('livewire.sales.z-report-import', compact('mealPeriodOptions', 'categories'));
    }
}
