<?php

namespace App\Livewire\Reports\Inventory;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\StockTakeLine;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class StockBalancePackage extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public ?int $categoryFilter = null;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function exportCsv()
    {
        $rows = $this->buildQuery()->get();

        return $this->exportCsvDownload('stock-balance-package.csv', [
            'Ingredient', 'Code', 'Category', 'Pack Size', 'UOM', 'Purchase Price', 'Current Cost', 'Last Stock Take Qty',
        ], $rows->map(fn ($r) => [
            $r->name, $r->code, $r->category_name, $r->pack_size, $r->uom,
            $r->purchase_price, $r->current_cost, $r->last_qty,
        ])->toArray());
    }

    public function render()
    {
        $items = $this->buildQuery()->paginate(25);
        $outlets = $this->getOutlets();
        $categories = IngredientCategory::roots()->active()->ordered()->with('children')->get();

        return view('livewire.reports.inventory.stock-balance-package', compact('items', 'outlets', 'categories'))
            ->layout('layouts.app', ['title' => 'Stock Balance (Package)']);
    }

    private function buildQuery()
    {
        // Subquery: latest stock take line per ingredient (filtered by outlet if needed)
        $latestStockTake = StockTakeLine::query()
            ->select('ingredient_id', DB::raw('MAX(stock_take_lines.id) as max_id'))
            ->join('stock_takes', 'stock_takes.id', '=', 'stock_take_lines.stock_take_id')
            ->when($this->outletFilter, fn ($q) => $q->where('stock_takes.outlet_id', $this->outletFilter))
            ->groupBy('ingredient_id');

        $query = Ingredient::query()
            ->select([
                'ingredients.id', 'ingredients.name', 'ingredients.code',
                'ingredients.pack_size', 'ingredients.purchase_price', 'ingredients.current_cost',
                'ic.name as category_name',
                'u.abbreviation as uom',
                'stl.actual_quantity as last_qty',
            ])
            ->leftJoin('ingredient_categories as ic', 'ic.id', '=', 'ingredients.ingredient_category_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'ingredients.base_uom_id')
            ->leftJoinSub($latestStockTake, 'lst', fn ($join) =>
                $join->on('lst.ingredient_id', '=', 'ingredients.id')
            )
            ->leftJoin('stock_take_lines as stl', 'stl.id', '=', 'lst.max_id')
            ->where('ingredients.is_active', true);

        if ($this->categoryFilter) {
            $cat = IngredientCategory::with('children')->find($this->categoryFilter);
            if ($cat) {
                $ids = $cat->children->isNotEmpty()
                    ? $cat->children->pluck('id')->push($cat->id)->toArray()
                    : [$cat->id];
                $query->whereIn('ingredients.ingredient_category_id', $ids);
            }
        }

        return $query->orderBy('ingredients.name');
    }
}
