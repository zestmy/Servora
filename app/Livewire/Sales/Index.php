<?php

namespace App\Livewire\Sales;

use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\SalesCategory;
use App\Models\SalesRecord;
use App\Models\SalesRecordLine;
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
        $this->setQuickRange('today');
    }

    #[On('z-report-saved')]
    public function refreshAfterImport(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        SalesRecord::findOrFail($id)->delete();
        session()->flash('success', 'Sales record deleted.');
    }

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

        // Build filtered query
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

        // Categories
        $categories = SalesCategory::active()->ordered()->get();

        // Revenue by category
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

        // Totals
        $totalRevenue = $records->sum(fn ($r) => (float) $r->total_revenue);
        $totalPax     = $records->sum('pax');
        $avgCheck     = ($totalPax > 0 && $totalRevenue > 0) ? round($totalRevenue / $totalPax, 2) : 0;

        // Daily breakdown
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

        // Missing dates
        $missingDates = $this->getMissingDates($records);

        // Calendar events
        $events = $this->getCalendarEvents();

        // Period label
        $periodLabel = $this->getPeriodLabel();

        $pdf = Pdf::loadView('pdf.sales-report', compact(
            'company', 'records', 'categories', 'categoryRevenues',
            'totalRevenue', 'totalPax', 'avgCheck', 'dailySales',
            'missingDates', 'events', 'periodLabel'
        ))->setPaper('a4', 'portrait');

        $filename = 'Sales-Report-' . ($this->dateFrom ?: 'all') . '-to-' . ($this->dateTo ?: 'all') . '.pdf';

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function render()
    {
        $query = SalesRecord::with(['lines.ingredientCategory', 'lines.salesCategory', 'attachments'])->withCount('attachments');
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

        // Stats — reflect current filters
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

        // Missing dates notice
        $missingDates = $this->getMissingDates(
            (clone $statsQ)->select('sale_date')->distinct()->get()
        );

        // Calendar events in range
        $events = $this->getCalendarEvents();

        $periodLabel       = $this->getPeriodLabel();
        $mealPeriodOptions = SalesRecord::mealPeriodOptions();

        return view('livewire.sales.index', compact(
            'records', 'filteredRevenue', 'filteredPax', 'filteredAvgCheck', 'filteredCount',
            'periodLabel', 'mealPeriodOptions', 'categoryRevenues', 'missingDates', 'events'
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

    private function getMissingDates($records): array
    {
        if (! $this->dateFrom || ! $this->dateTo) {
            return [];
        }

        $start = Carbon::parse($this->dateFrom);
        $end   = Carbon::parse($this->dateTo);

        // Don't check future dates or ranges > 366 days
        if ($start->isFuture() || $start->diffInDays($end) > 366) {
            return [];
        }

        $endCapped = $end->isFuture() ? now()->startOfDay() : $end;

        $existingDates = $records instanceof \Illuminate\Support\Collection
            ? $records->pluck('sale_date')->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))->unique()->toArray()
            : collect($records)->pluck('sale_date')->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))->unique()->toArray();

        $missing = [];
        foreach (CarbonPeriod::create($start, $endCapped) as $date) {
            if (! in_array($date->format('Y-m-d'), $existingDates)) {
                $missing[] = $date->format('d M Y (l)');
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
