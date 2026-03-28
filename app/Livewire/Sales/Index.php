<?php

namespace App\Livewire\Sales;

use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\SalesCategory;
use App\Models\SalesClosure;
use App\Models\SalesRecord;
use App\Models\SalesRecordLine;
use App\Models\SalesTarget;
use App\Services\AiAnalyticsService;
use App\Services\CsvExportService;
use App\Traits\ScopesToActiveOutlet;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, ScopesToActiveOutlet;

    public string $search           = '';
    public string $dateFrom         = '';
    public string $dateTo           = '';
    public string $mealPeriodFilter = '';
    public string $quickRange       = 'today';

    public array $selected   = [];
    public bool  $selectAll  = false;
    public bool  $canDelete  = false;

    // Closure form
    public bool   $showClosureModal = false;
    public string $closureDate      = '';
    public string $closureReason    = '';
    public string $closureCustom    = '';
    public string $closureNotes     = '';
    public ?int   $editingClosureId = null;

    // AI Predictive
    public bool   $loadingPrediction = false;
    public ?array $prediction        = null;
    public ?string $predictionError  = null;

    public function updatedSearch(): void           { $this->resetPage(); }
    public function updatedDateFrom(): void         { $this->quickRange = 'custom'; $this->resetPage(); }
    public function updatedDateTo(): void           { $this->quickRange = 'custom'; $this->resetPage(); }
    public function updatedMealPeriodFilter(): void { $this->resetPage(); }

    public function setQuickRange(string $range): void
    {
        $this->quickRange = $range;

        $today = now();
        match ($range) {
            'today'      => [$this->dateFrom, $this->dateTo] = [$today->format('Y-m-d'), $today->format('Y-m-d')],
            'yesterday'  => [$this->dateFrom, $this->dateTo] = [$today->copy()->subDay()->format('Y-m-d'), $today->copy()->subDay()->format('Y-m-d')],
            'last_7'     => [$this->dateFrom, $this->dateTo] = [$today->copy()->subDays(6)->format('Y-m-d'), $today->format('Y-m-d')],
            'last_30'    => [$this->dateFrom, $this->dateTo] = [$today->copy()->subDays(29)->format('Y-m-d'), $today->format('Y-m-d')],
            'this_week'  => [$this->dateFrom, $this->dateTo] = [$today->copy()->startOfWeek()->format('Y-m-d'), $today->format('Y-m-d')],
            'this_month' => [$this->dateFrom, $this->dateTo] = [$today->copy()->startOfMonth()->format('Y-m-d'), $today->format('Y-m-d')],
            'last_month' => [$this->dateFrom, $this->dateTo] = [$today->copy()->subMonth()->startOfMonth()->format('Y-m-d'), $today->copy()->subMonth()->endOfMonth()->format('Y-m-d')],
            'last_year'  => [$this->dateFrom, $this->dateTo] = [$today->copy()->subYear()->startOfYear()->format('Y-m-d'), $today->copy()->subYear()->endOfYear()->format('Y-m-d')],
            'this_year'  => [$this->dateFrom, $this->dateTo] = [$today->copy()->startOfYear()->format('Y-m-d'), $today->format('Y-m-d')],
            'all'        => [$this->dateFrom, $this->dateTo] = ['', ''],
            default      => null,
        };

        $this->resetPage();
    }

    public function mount(): void
    {
        $this->canDelete = Auth::user()->isSystemRole() || Auth::user()->hasCapability('can_delete_records');
        $this->setQuickRange('today');
    }

    public function updatedSelectAll(bool $value): void
    {
        // Handled by Alpine on the frontend
    }

    #[On('z-report-saved')]
    public function refreshAfterImport(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        if (! $this->canDelete) {
            session()->flash('error', 'You do not have permission to delete sales records.');
            return;
        }

        SalesRecord::findOrFail($id)->delete();
        session()->flash('success', 'Sales record deleted.');
    }

    public function bulkDelete(): void
    {
        if (! $this->canDelete) {
            session()->flash('error', 'You do not have permission to delete sales records.');
            return;
        }

        if (empty($this->selected)) {
            return;
        }

        $count = SalesRecord::whereIn('id', $this->selected)->delete();
        $this->selected  = [];
        $this->selectAll = false;
        session()->flash('success', $count . ' sales record(s) deleted.');
    }

    // ── Closure Management ──────────────────────────────────────────────

    public function openClosureModal(string $date = ''): void
    {
        $this->resetClosureForm();
        $this->closureDate = $date;

        // Check if closure already exists for this date
        if ($date) {
            $user    = Auth::user();
            $outletId = $user->activeOutletId() ?: Outlet::where('company_id', $user->company_id)->value('id');

            $existing = SalesClosure::where('closure_date', $date)
                ->where(fn ($q) => $q->where('outlet_id', $outletId)->orWhereNull('outlet_id'))
                ->first();

            if ($existing) {
                $this->editingClosureId = $existing->id;
                $this->closureNotes     = $existing->notes ?? '';

                // Check if reason matches a common reason
                if (in_array($existing->reason, SalesClosure::commonReasons())) {
                    $this->closureReason = $existing->reason;
                } else {
                    $this->closureReason = 'custom';
                    $this->closureCustom = $existing->reason;
                }
            }
        }

        $this->showClosureModal = true;
    }

    public function saveClosure(): void
    {
        $reason = $this->closureReason === 'custom'
            ? trim($this->closureCustom)
            : $this->closureReason;

        if (! $reason) {
            $this->addError('closureReason', 'Please select or enter a reason.');
            return;
        }

        if (! $this->closureDate) {
            $this->addError('closureDate', 'Please select a date.');
            return;
        }

        $user      = Auth::user();
        $companyId = $user->company_id;
        $outletId  = $user->activeOutletId() ?: Outlet::where('company_id', $companyId)->value('id');

        if ($this->editingClosureId) {
            $closure = SalesClosure::findOrFail($this->editingClosureId);
            $closure->update([
                'reason' => $reason,
                'notes'  => $this->closureNotes ?: null,
            ]);
        } else {
            SalesClosure::updateOrCreate(
                [
                    'company_id'   => $companyId,
                    'outlet_id'    => $outletId,
                    'closure_date' => $this->closureDate,
                ],
                [
                    'reason'     => $reason,
                    'notes'      => $this->closureNotes ?: null,
                    'created_by' => $user->id,
                ]
            );
        }

        $this->showClosureModal = false;
        $this->resetClosureForm();
    }

    public function removeClosure(int $id): void
    {
        SalesClosure::findOrFail($id)->delete();
    }

    private function resetClosureForm(): void
    {
        $this->closureDate      = '';
        $this->closureReason    = '';
        $this->closureCustom    = '';
        $this->closureNotes     = '';
        $this->editingClosureId = null;
        $this->resetErrorBag();
    }

    // ── Exports ─────────────────────────────────────────────────────────

    public function exportCsv()
    {
        $query = SalesRecord::with('lines.salesCategory');
        $this->scopeByOutlet($query);

        if ($this->dateFrom) {
            $query->where('sale_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('sale_date', '<=', $this->dateTo);
        }
        if ($this->mealPeriodFilter) {
            $query->where('meal_period', $this->mealPeriodFilter);
        }

        $records = $query->orderByDesc('sale_date')->get();

        $categories = SalesCategory::active()->ordered()->get();
        $categoryNames = $categories->pluck('name')->toArray();
        $categoryIds = $categories->pluck('id')->toArray();

        $headers = array_merge(['Date', 'Reference', 'Meal Period', 'Pax'], $categoryNames, ['Total Revenue']);

        if ($records->isEmpty()) {
            $rows = collect([
                array_merge(['2026-03-10', 'INV-001', 'Lunch', '50'], array_fill(0, count($categoryIds), '1500.00'), ['4500.00']),
                array_merge(['2026-03-10', 'INV-002', 'Dinner', '80'], array_fill(0, count($categoryIds), '2000.00'), ['6000.00']),
            ]);
        } else {
            $rows = $records->map(function ($r) use ($categoryIds) {
                $linesByCat = $r->lines->keyBy('sales_category_id');

                $categoryRevenues = [];
                foreach ($categoryIds as $catId) {
                    $line = $linesByCat->get($catId);
                    $categoryRevenues[] = $line ? $line->total_revenue : '0';
                }

                return array_merge([
                    $r->sale_date->format('Y-m-d'),
                    $r->reference_number ?? '',
                    $r->mealPeriodLabel(),
                    $r->pax ?? '',
                ], $categoryRevenues, [
                    $r->total_revenue,
                ]);
            });
        }

        return CsvExportService::download('sales-records.csv', $headers, $rows);
    }

    public function exportPdf()
    {
        $user    = Auth::user();
        $company = Company::find($user->company_id);
        $outletId = $user->activeOutletId() ?: Outlet::where('company_id', $user->company_id)->value('id');
        $outlet  = $outletId ? Outlet::find($outletId) : null;

        $query = SalesRecord::with('lines.salesCategory');
        $this->scopeByOutlet($query);

        if ($this->dateFrom) {
            $query->where('sale_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('sale_date', '<=', $this->dateTo);
        }
        if ($this->mealPeriodFilter) {
            $query->where('meal_period', $this->mealPeriodFilter);
        }

        $records = $query->orderBy('sale_date')->orderByDesc('id')->get();

        $categories = SalesCategory::active()->ordered()->get();

        $categoryRevenues = [];
        foreach ($categories as $cat) {
            $total = $records->flatMap->lines
                ->where('sales_category_id', $cat->id)
                ->sum(fn ($l) => (float) $l->total_revenue);
            if ($total > 0) {
                $categoryRevenues[] = [
                    'name'    => $cat->name,
                    'revenue' => $total,
                ];
            }
        }

        $totalRevenue = $records->sum(fn ($r) => (float) $r->total_revenue);
        $totalPax     = $records->sum('pax');
        $avgCheck     = ($totalPax > 0 && $totalRevenue > 0) ? round($totalRevenue / $totalPax, 2) : 0;

        $dailySales = $records->groupBy(fn ($r) => $r->sale_date->format('Y-m-d'))
            ->map(function ($dayRecords, $date) {
                $rev = $dayRecords->sum(fn ($r) => (float) $r->total_revenue);
                $pax = $dayRecords->sum('pax');
                return [
                    'date'    => Carbon::parse($date)->format('d M Y'),
                    'day'     => Carbon::parse($date)->format('l'),
                    'revenue' => $rev,
                    'pax'     => $pax,
                    'avg'     => ($pax > 0 && $rev > 0) ? round($rev / $pax, 2) : 0,
                    'count'   => $dayRecords->count(),
                ];
            })->values()->toArray();

        $missingDatesData = $this->getMissingDatesWithClosures($records);
        $missingDates     = collect($missingDatesData)->pluck('label')->toArray();

        $events      = $this->getCalendarEvents();
        $periodLabel = $this->getPeriodLabel();

        $pdf = Pdf::loadView('pdf.sales-report', compact(
            'company', 'outlet', 'records', 'categories', 'categoryRevenues',
            'totalRevenue', 'totalPax', 'avgCheck', 'dailySales',
            'missingDates', 'missingDatesData', 'events', 'periodLabel'
        ))->setPaper('a4', 'portrait');

        $filename = 'Sales-Report-' . ($this->dateFrom ?: 'all') . '-to-' . ($this->dateTo ?: 'all') . '.pdf';

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    // ── AI Predictive ─────────────────────────────────────────────────

    public function generatePrediction(): void
    {
        $this->loadingPrediction = true;
        $this->prediction = null;
        $this->predictionError = null;

        try {
            $user      = Auth::user();
            $outletId  = $user->activeOutletId() ?: Outlet::where('company_id', $user->company_id)->value('id');

            // Use current month for context
            $period = now()->format('Y-m');

            $service = app(AiAnalyticsService::class);
            $result = $service->analyze(
                $period,
                $outletId,
                'custom',
                "Based on the historical sales data provided, generate a predictive sales forecast for the next month. Include:\n"
                . "1. **Predicted Revenue Range** — provide a low/mid/high estimate\n"
                . "2. **Predicted Daily Average** — expected average daily revenue\n"
                . "3. **Best & Worst Days** — which days of week are likely strongest/weakest\n"
                . "4. **Key Factors** — what will influence next month's performance (events, trends, seasonality)\n"
                . "5. **Confidence Level** — how reliable is this prediction based on available data\n"
                . "Keep it concise and actionable. Use bullet points."
            );

            $this->prediction = $result;
        } catch (\Throwable $e) {
            $this->predictionError = $e->getMessage();
        } finally {
            $this->loadingPrediction = false;
        }
    }

    // ── Render ──────────────────────────────────────────────────────────

    public function render()
    {
        $query = SalesRecord::with(['lines.salesCategory', 'attachments'])->withCount('attachments');
        $this->scopeByOutlet($query);

        if ($this->search) {
            $query->where('reference_number', 'like', '%' . $this->search . '%');
        }
        if ($this->dateFrom) {
            $query->where('sale_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('sale_date', '<=', $this->dateTo);
        }
        if ($this->mealPeriodFilter) {
            $query->where('meal_period', $this->mealPeriodFilter);
        }

        $records = $query->orderByDesc('sale_date')->orderByDesc('id')->paginate(20);

        // Stats
        $statsQ = SalesRecord::query();
        $this->scopeByOutlet($statsQ);
        if ($this->dateFrom) {
            $statsQ->where('sale_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $statsQ->where('sale_date', '<=', $this->dateTo);
        }
        if ($this->mealPeriodFilter) {
            $statsQ->where('meal_period', $this->mealPeriodFilter);
        }

        $filteredRevenue  = (clone $statsQ)->sum('total_revenue');
        $filteredPax      = (clone $statsQ)->sum('pax');
        $filteredCount    = (clone $statsQ)->count();
        $filteredAvgCheck = ($filteredPax > 0 && $filteredRevenue > 0)
            ? round($filteredRevenue / $filteredPax, 2)
            : null;

        // Revenue by sales category
        $categories = SalesCategory::active()->ordered()->get();
        $recordIds  = (clone $statsQ)->pluck('id');
        $categoryRevenues = [];

        if ($recordIds->isNotEmpty()) {
            $catTotals = SalesRecordLine::whereIn('sales_record_id', $recordIds)
                ->whereNotNull('sales_category_id')
                ->selectRaw('sales_category_id, SUM(total_revenue) as total')
                ->groupBy('sales_category_id')
                ->pluck('total', 'sales_category_id');

            foreach ($categories as $cat) {
                $rev = (float) ($catTotals[$cat->id] ?? 0);
                if ($rev > 0) {
                    $categoryRevenues[] = [
                        'name'    => $cat->name,
                        'color'   => $cat->color ?? '#6b7280',
                        'revenue' => $rev,
                        'pct'     => $filteredRevenue > 0 ? round($rev / $filteredRevenue * 100, 1) : 0,
                    ];
                }
            }
        }

        // Missing dates with closure reasons
        $missingDatesData = $this->getMissingDatesWithClosures(
            (clone $statsQ)->select('sale_date')->distinct()->get()
        );

        // Calendar events
        $events = $this->getCalendarEvents();

        $periodLabel       = $this->getPeriodLabel();
        $commonReasons     = SalesClosure::commonReasons();
        $mealPeriodOptions = SalesRecord::mealPeriodOptions();

        // Sales target — always current month regardless of filters
        $targetData = null;
        {
            $user      = Auth::user();
            $outletId  = $user->activeOutletId() ?: Outlet::where('company_id', $user->company_id)->value('id');
            $targetPeriod = now()->format('Y-m');

            $target = SalesTarget::where('period', $targetPeriod)
                ->where(fn ($q) => $q->where('outlet_id', $outletId)->orWhereNull('outlet_id'))
                ->orderByRaw('outlet_id IS NULL ASC') // prefer outlet-specific
                ->first();

            if ($target) {
                // Current month actual revenue (unfiltered)
                $monthStart = now()->startOfMonth()->toDateString();
                $monthEnd   = now()->endOfMonth()->toDateString();
                $monthQ     = SalesRecord::query();
                $this->scopeByOutlet($monthQ);
                $monthRevenue = (clone $monthQ)->whereBetween('sale_date', [$monthStart, $monthEnd])->sum('total_revenue');
                $monthPax     = (clone $monthQ)->whereBetween('sale_date', [$monthStart, $monthEnd])->sum('pax');

                $pct = $target->target_revenue > 0
                    ? round($monthRevenue / $target->target_revenue * 100, 1)
                    : 0;
                $paxPct = ($target->target_pax && $target->target_pax > 0)
                    ? round($monthPax / $target->target_pax * 100, 1)
                    : null;

                $targetData = [
                    'revenue'      => (float) $target->target_revenue,
                    'pax'          => $target->target_pax,
                    'revenue_pct'  => $pct,
                    'pax_pct'      => $paxPct,
                    'notes'        => $target->notes,
                    'actual'       => (float) $monthRevenue,
                    'actual_pax'   => (int) $monthPax,
                ];
            }
        }

        return view('livewire.sales.index', compact(
            'records', 'filteredRevenue', 'filteredPax', 'filteredAvgCheck', 'filteredCount',
            'periodLabel', 'mealPeriodOptions', 'categoryRevenues', 'missingDatesData',
            'events', 'commonReasons', 'targetData'
        ))->layout('layouts.app', ['title' => 'Sales']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function getPeriodLabel(): string
    {
        return match ($this->quickRange) {
            'today'      => 'Today',
            'yesterday'  => 'Yesterday',
            'last_7'     => 'Last 7 Days',
            'last_30'    => 'Last 30 Days',
            'this_week'  => 'This Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'last_year'  => 'Last Year',
            'this_year'  => 'This Year',
            'all'        => 'All Time',
            'custom'     => 'Custom Range',
            default      => 'Today',
        };
    }

    private function getMissingDatesWithClosures($records): array
    {
        if (! $this->dateFrom || ! $this->dateTo) {
            return [];
        }

        $start = Carbon::parse($this->dateFrom);
        $end   = Carbon::parse($this->dateTo);

        if ($start->isFuture() || $start->diffInDays($end) > 366) {
            return [];
        }

        $endCapped = $end->isFuture() ? now()->startOfDay() : $end;

        $existingDates = $records instanceof \Illuminate\Support\Collection
            ? $records->pluck('sale_date')->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))->unique()->toArray()
            : collect($records)->pluck('sale_date')->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))->unique()->toArray();

        // Load closures for the date range
        $closures = SalesClosure::whereBetween('closure_date', [$start->format('Y-m-d'), $endCapped->format('Y-m-d')])
            ->get()
            ->keyBy(fn ($c) => $c->closure_date->format('Y-m-d'));

        $missing = [];
        foreach (CarbonPeriod::create($start, $endCapped) as $date) {
            $dateStr = $date->format('Y-m-d');
            if (! in_array($dateStr, $existingDates)) {
                $closure = $closures[$dateStr] ?? null;
                $missing[] = [
                    'date'       => $dateStr,
                    'label'      => $date->format('d M Y (l)'),
                    'closure_id' => $closure?->id,
                    'reason'     => $closure?->reason,
                    'notes'      => $closure?->notes,
                ];
            }
        }

        return $missing;
    }

    private function getCalendarEvents(): array
    {
        if (! $this->dateFrom || ! $this->dateTo) {
            return [];
        }

        return CalendarEvent::where(function ($q) {
                $q->whereBetween('event_date', [$this->dateFrom, $this->dateTo])
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('end_date')
                         ->where('event_date', '<=', $this->dateTo)
                         ->where('end_date', '>=', $this->dateFrom);
                  });
            })
            ->orderBy('event_date')
            ->get()
            ->map(fn ($e) => [
                'title'    => $e->title,
                'date'     => $e->event_date->format('d M Y'),
                'end_date' => $e->end_date?->format('d M Y'),
                'category' => $e->categoryLabel(),
                'impact'   => $e->impact,
            ])
            ->toArray();
    }
}
