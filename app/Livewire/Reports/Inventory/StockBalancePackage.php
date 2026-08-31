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
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Stock Balance (Package)']);
    }

    /**
     * Lines that may answer "last counted".
     *
     * A count is a COMPLETED stock take that still exists. Reading any stock
     * take row meant this column was fed by half-filled drafts and by counts
     * somebody had deleted — and since a draft starts every line at zero, the
     * report showed a confident 0 for items nobody had counted yet.
     */
    private function countedLines()
    {
        return StockTakeLine::query()
            ->join('stock_takes', 'stock_takes.id', '=', 'stock_take_lines.stock_take_id')
            ->where('stock_takes.status', 'completed')
            ->whereNull('stock_takes.deleted_at')
            ->when($this->outletFilter, fn ($q) => $q->where('stock_takes.outlet_id', $this->outletFilter));
    }

    private function buildQuery()
    {
        // "Last" is the latest count DATE, not the highest row id: saving a stock
        // take rewrites its lines, so ids track when a sheet was last touched
        // rather than when the stock was counted. Take the newest date per
        // ingredient, then the last line written on that date to break ties.
        $latestDate = $this->countedLines()
            ->select('stock_take_lines.ingredient_id', DB::raw('MAX(stock_takes.stock_take_date) as max_date'))
            ->groupBy('stock_take_lines.ingredient_id');

        $latestStockTake = $this->countedLines()
            ->joinSub($latestDate, 'ld', fn ($join) => $join
                ->on('ld.ingredient_id', '=', 'stock_take_lines.ingredient_id')
                ->on('ld.max_date', '=', 'stock_takes.stock_take_date'))
            ->select('stock_take_lines.ingredient_id', DB::raw('MAX(stock_take_lines.id) as max_id'))
            ->groupBy('stock_take_lines.ingredient_id');

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
