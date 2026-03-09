<?php

namespace App\Livewire\Sales;

use App\Models\SalesCategory;
use App\Models\SalesRecord;
use App\Services\CsvExportService;
use App\Traits\ScopesToActiveOutlet;
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

        // Get all active sales categories for column headers
        $categories = SalesCategory::active()->ordered()->get();
        $categoryNames = $categories->pluck('name')->toArray();
        $categoryIds = $categories->pluck('id')->toArray();

        $headers = array_merge(['Date', 'Reference', 'Meal Period', 'Pax'], $categoryNames, ['Total Revenue']);

        if ($records->isEmpty()) {
            // Include sample rows so the format is clear
            $sampleCatRevenues = array_map(fn () => '0', $categoryIds);
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

    public function render()
    {
        $query = SalesRecord::with(['lines.ingredientCategory', 'attachments'])->withCount('attachments');
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

        $filteredRevenue = (clone $statsQ)->sum('total_revenue');
        $filteredPax     = (clone $statsQ)->sum('pax');
        $filteredCount   = (clone $statsQ)->count();
        $filteredAvgCheck = ($filteredPax > 0 && $filteredRevenue > 0)
            ? round($filteredRevenue / $filteredPax, 2)
            : null;

        // Period label for stats cards
        $periodLabel = match ($this->quickRange) {
            'today'      => 'Today',
            'yesterday'  => 'Yesterday',
            'last_7'     => 'Last 7 Days',
            'last_30'    => 'Last 30 Days',
            'this_week'  => 'This Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'this_year'  => 'This Year',
            'all'        => 'All Time',
            'custom'     => 'Custom Range',
            default      => 'Today',
        };

        $mealPeriodOptions = SalesRecord::mealPeriodOptions();

        return view('livewire.sales.index', compact(
            'records', 'filteredRevenue', 'filteredPax', 'filteredAvgCheck', 'filteredCount', 'periodLabel', 'mealPeriodOptions'
        ))->layout('layouts.app', ['title' => 'Sales']);
    }
}
