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

    public function updatedSearch(): void           { $this->resetPage(); }
    public function updatedDateFrom(): void         { $this->resetPage(); }
    public function updatedDateTo(): void           { $this->resetPage(); }
    public function updatedMealPeriodFilter(): void { $this->resetPage(); }

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

        // Stats
        $todayRevQ = SalesRecord::whereDate('sale_date', today());
        $this->scopeByOutlet($todayRevQ);
        $todayRevenue = $todayRevQ->sum('total_revenue');

        $todayPaxQ = SalesRecord::whereDate('sale_date', today());
        $this->scopeByOutlet($todayPaxQ);
        $todayPax = $todayPaxQ->sum('pax');

        $todayAvgCheck = ($todayPax > 0 && $todayRevenue > 0)
            ? round($todayRevenue / $todayPax, 2)
            : null;

        $monthRevQ = SalesRecord::whereMonth('sale_date', now()->month)->whereYear('sale_date', now()->year);
        $this->scopeByOutlet($monthRevQ);
        $monthRevenue = $monthRevQ->sum('total_revenue');

        $mealPeriodOptions = SalesRecord::mealPeriodOptions();

        return view('livewire.sales.index', compact(
            'records', 'todayRevenue', 'todayPax', 'todayAvgCheck', 'monthRevenue', 'mealPeriodOptions'
        ))->layout('layouts.app', ['title' => 'Sales']);
    }
}
