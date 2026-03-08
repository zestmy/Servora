<?php

namespace App\Livewire\Sales;

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
        $query = SalesRecord::with('lines.ingredientCategory');
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

        $headers = ['Date', 'Reference', 'Meal Period', 'Pax', 'Revenue'];
        $rows = $records->map(fn ($r) => [
            $r->sale_date->format('Y-m-d'),
            $r->reference_number ?? '',
            $r->mealPeriodLabel(),
            $r->pax ?? '',
            $r->total_revenue,
        ]);

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
