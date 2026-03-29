<?php

namespace App\Livewire\Reports\Menu;

use App\Models\SalesRecordLine;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class SalesMenuIngredients extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function exportCsv()
    {
        $rows = $this->buildQuery()->get();

        return $this->exportCsvDownload('sales-menu-ingredients.csv', [
            'Recipe', 'Category', 'Units Sold', 'Revenue', 'Ingredient Cost', 'Gross Profit', 'Cost %',
        ], $rows->map(fn ($r) => [
            $r->item_name, $r->category_name, $r->units_sold,
            number_format($r->revenue, 2), number_format($r->ingredient_cost, 2),
            number_format($r->gross_profit, 2), number_format($r->cost_pct, 1) . '%',
        ])->toArray());
    }

    public function render()
    {
        $items = $this->buildQuery()->paginate(25);
        $outlets = $this->getOutlets();

        return view('livewire.reports.menu.sales-menu-ingredients', compact('items', 'outlets'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Sales Menu & Ingredients']);
    }

    private function buildQuery()
    {
        return SalesRecordLine::query()
            ->select([
                'sales_record_lines.recipe_id',
                DB::raw("COALESCE(sales_record_lines.item_name, r.name) as item_name"),
                DB::raw("COALESCE(ic.name, '') as category_name"),
                DB::raw('SUM(sales_record_lines.quantity) as units_sold'),
                DB::raw('SUM(sales_record_lines.total_revenue) as revenue'),
                DB::raw('SUM(sales_record_lines.total_cost) as ingredient_cost'),
                DB::raw('SUM(sales_record_lines.total_revenue) - SUM(sales_record_lines.total_cost) as gross_profit'),
                DB::raw("CASE WHEN SUM(sales_record_lines.total_revenue) > 0
                    THEN ROUND((SUM(sales_record_lines.total_cost) / SUM(sales_record_lines.total_revenue)) * 100, 1)
                    ELSE 0 END as cost_pct"),
            ])
            ->join('sales_records as sr', 'sr.id', '=', 'sales_record_lines.sales_record_id')
            ->leftJoin('recipes as r', 'r.id', '=', 'sales_record_lines.recipe_id')
            ->leftJoin('ingredient_categories as ic', 'ic.id', '=', 'sales_record_lines.ingredient_category_id')
            ->whereBetween('sr.sale_date', [$this->dateFrom, $this->dateTo])
            ->whereNull('sr.deleted_at')
            ->when($this->outletFilter, fn ($q) => $q->where('sr.outlet_id', $this->outletFilter))
            ->groupBy('sales_record_lines.recipe_id', 'sales_record_lines.item_name', 'r.name', 'ic.name')
            ->orderByDesc('revenue');
    }
}
