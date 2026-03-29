<?php

namespace App\Livewire\Reports\InventoryAction;

use App\Models\StockTakeLine;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class StockCountAnalysis extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function exportCsv()
    {
        $rows = $this->buildQuery()->get();

        return $this->exportCsvDownload('stock-count-analysis.csv', [
            'Ingredient', 'Expected Qty', 'Counted Qty', 'Variance Qty', 'Variance %', 'Value Variance',
        ], $rows->map(fn ($r) => [
            $r->ingredient_name, $r->system_quantity, $r->actual_quantity,
            $r->variance_quantity, $r->variance_pct, $r->variance_cost,
        ])->toArray());
    }

    public function render()
    {
        $lines = $this->buildQuery()->paginate(25);
        $outlets = $this->getOutlets();

        return view('livewire.reports.inventory-action.stock-count-analysis', compact('lines', 'outlets'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Stock Count Analysis']);
    }

    private function buildQuery()
    {
        return StockTakeLine::query()
            ->select([
                'stock_take_lines.*',
                'i.name as ingredient_name',
                'i.code as ingredient_code',
                'u.abbreviation as uom',
                DB::raw("CASE WHEN stock_take_lines.system_quantity != 0
                    THEN ROUND((stock_take_lines.variance_quantity / stock_take_lines.system_quantity) * 100, 2)
                    ELSE 0 END as variance_pct"),
            ])
            ->join('stock_takes as st', 'st.id', '=', 'stock_take_lines.stock_take_id')
            ->join('ingredients as i', 'i.id', '=', 'stock_take_lines.ingredient_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'stock_take_lines.uom_id')
            ->when($this->outletFilter, fn ($q) => $q->where('st.outlet_id', $this->outletFilter))
            ->whereBetween('st.stock_take_date', [$this->dateFrom, $this->dateTo])
            ->whereNull('st.deleted_at')
            ->orderBy('i.name');
    }
}
