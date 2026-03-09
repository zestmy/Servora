<?php

namespace App\Livewire\Inventory;

use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use App\Models\Recipe;
use App\Models\StaffMealRecord;
use App\Models\StockTake;
use App\Models\WastageRecord;
use App\Traits\ScopesToActiveOutlet;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, ScopesToActiveOutlet;

    public string $tab          = 'prep-items';
    public string $search       = '';
    public string $dateFrom     = '';
    public string $dateTo       = '';
    public string $statusFilter = '';

    public function updatedTab(): void      { $this->resetPage(); $this->search = ''; $this->dateFrom = ''; $this->dateTo = ''; $this->statusFilter = ''; }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedSearch(): void   { $this->resetPage(); }
    public function updatedDateFrom(): void { $this->resetPage(); }
    public function updatedDateTo(): void   { $this->resetPage(); }

    public function deleteStockTake(int $id): void
    {
        StockTake::findOrFail($id)->delete();
        session()->flash('success', 'Stock take deleted.');
    }

    public function deleteWastage(int $id): void
    {
        WastageRecord::findOrFail($id)->delete();
        session()->flash('success', 'Wastage record deleted.');
    }

    public function deleteStaffMeal(int $id): void
    {
        StaffMealRecord::findOrFail($id)->delete();
        session()->flash('success', 'Staff meal record deleted.');
    }

    public function deleteTransfer(int $id): void
    {
        $transfer = OutletTransfer::findOrFail($id);
        if ($transfer->status !== 'draft') {
            session()->flash('error', 'Only draft transfers can be deleted.');
            return;
        }
        $transfer->delete();
        session()->flash('success', 'Transfer deleted.');
    }

    public function deletePrepItem(int $recipeId): void
    {
        $recipe = Recipe::with('ingredient')->findOrFail($recipeId);
        // Also soft-delete the synced ingredient record
        $recipe->ingredient?->delete();
        $recipe->delete();
        session()->flash('success', 'Prep item deleted.');
    }

    public function render()
    {
        // ── Stock Takes ───────────────────────────────────────────────────
        $stockQuery = StockTake::withCount('lines');
        $this->scopeByOutlet($stockQuery);

        if ($this->search && $this->tab === 'stock-takes') {
            $stockQuery->where('reference_number', 'like', '%' . $this->search . '%');
        }
        if ($this->dateFrom && $this->tab === 'stock-takes') {
            $stockQuery->where('stock_take_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo && $this->tab === 'stock-takes') {
            $stockQuery->where('stock_take_date', '<=', $this->dateTo);
        }

        $stockTakes = $this->tab === 'stock-takes'
            ? $stockQuery->orderByDesc('stock_take_date')->orderByDesc('id')->paginate(15)
            : collect();

        // ── Wastage Records ───────────────────────────────────────────────
        $wastageQuery = WastageRecord::withCount('lines');
        $this->scopeByOutlet($wastageQuery);

        if ($this->search && $this->tab === 'wastage') {
            $wastageQuery->where('reference_number', 'like', '%' . $this->search . '%');
        }
        if ($this->dateFrom && $this->tab === 'wastage') {
            $wastageQuery->where('wastage_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo && $this->tab === 'wastage') {
            $wastageQuery->where('wastage_date', '<=', $this->dateTo);
        }

        $wastageRecords = $this->tab === 'wastage'
            ? $wastageQuery->orderByDesc('wastage_date')->orderByDesc('id')->paginate(15)
            : collect();

        // ── Staff Meal Records ───────────────────────────────────────────
        $staffMealQuery = StaffMealRecord::withCount('lines');
        $this->scopeByOutlet($staffMealQuery);

        if ($this->search && $this->tab === 'staff-meals') {
            $staffMealQuery->where('reference_number', 'like', '%' . $this->search . '%');
        }
        if ($this->dateFrom && $this->tab === 'staff-meals') {
            $staffMealQuery->where('meal_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo && $this->tab === 'staff-meals') {
            $staffMealQuery->where('meal_date', '<=', $this->dateTo);
        }

        $staffMealRecords = $this->tab === 'staff-meals'
            ? $staffMealQuery->orderByDesc('meal_date')->orderByDesc('id')->paginate(15)
            : collect();

        // ── Prep Items ────────────────────────────────────────────────────
        $prepSearch = $this->tab === 'prep-items' ? $this->search : '';
        $prepItems = $this->tab === 'prep-items'
            ? Recipe::with(['yieldUom', 'ingredient'])
                ->where('is_prep', true)
                ->when($prepSearch, fn ($q) => $q->where(function ($q) use ($prepSearch) {
                    $q->where('name', 'like', '%' . $prepSearch . '%')
                      ->orWhere('code', 'like', '%' . $prepSearch . '%');
                }))
                ->orderBy('name')
                ->paginate(15)
            : collect();

        // ── Transfers ────────────────────────────────────────────────────
        $outletId = $this->activeOutletId();
        $transferQuery = OutletTransfer::withCount('lines')
            ->with(['fromOutlet', 'toOutlet']);

        if ($outletId) {
            $transferQuery->where(function ($q) use ($outletId) {
                $q->where('from_outlet_id', $outletId)
                  ->orWhere('to_outlet_id', $outletId);
            });
        }

        if ($this->statusFilter && $this->tab === 'transfers') {
            $transferQuery->where('status', $this->statusFilter);
        }
        if ($this->search && $this->tab === 'transfers') {
            $transferQuery->where('transfer_number', 'like', '%' . $this->search . '%');
        }
        if ($this->dateFrom && $this->tab === 'transfers') {
            $transferQuery->where('transfer_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo && $this->tab === 'transfers') {
            $transferQuery->where('transfer_date', '<=', $this->dateTo);
        }

        $transfers = $this->tab === 'transfers'
            ? $transferQuery->orderByDesc('transfer_date')->orderByDesc('id')->paginate(15)
            : collect();

        // ── Stats ─────────────────────────────────────────────────────────
        $wastageStatQ = WastageRecord::whereMonth('wastage_date', now()->month)
            ->whereYear('wastage_date', now()->year);
        $this->scopeByOutlet($wastageStatQ);
        $monthWastageCost = $wastageStatQ->sum('total_cost');

        $stStatQ = StockTake::whereMonth('stock_take_date', now()->month)
            ->whereYear('stock_take_date', now()->year);
        $this->scopeByOutlet($stStatQ);
        $monthStockTakes = $stStatQ->count();

        $draftQ = StockTake::where('status', 'draft');
        $this->scopeByOutlet($draftQ);
        $draftStockTakes = $draftQ->count();

        $totalWastQ = WastageRecord::query();
        $this->scopeByOutlet($totalWastQ);
        $totalWastageCost = $totalWastQ->sum('total_cost');

        $staffMealStatQ = StaffMealRecord::whereMonth('meal_date', now()->month)
            ->whereYear('meal_date', now()->year);
        $this->scopeByOutlet($staffMealStatQ);
        $monthStaffMealCost = $staffMealStatQ->sum('total_cost');

        $prepItemCount = Recipe::where('is_prep', true)->count();

        $inTransitQ = OutletTransfer::where('status', 'in_transit');
        if ($outletId) {
            $inTransitQ->where(function ($q) use ($outletId) {
                $q->where('from_outlet_id', $outletId)->orWhere('to_outlet_id', $outletId);
            });
        }
        $inTransitCount = $inTransitQ->count();

        $latestStQ = StockTake::where('status', 'completed');
        $this->scopeByOutlet($latestStQ);
        $latestStockTake = $latestStQ->orderByDesc('stock_take_date')
            ->orderByDesc('id')
            ->first(['id', 'stock_take_date', 'total_stock_cost']);

        // ── Cost by Category ──────────────────────────────────────────────
        $categoryBreakdown = null;

        if ($latestStockTake) {
            $lines = $latestStockTake->lines()
                ->with(['ingredient.ingredientCategory.parent'])
                ->get();

            $groups = [];

            foreach ($lines as $line) {
                $lineCost   = floatval($line->actual_quantity) * floatval($line->unit_cost);
                $ingredient = $line->ingredient;
                $cat        = $ingredient?->ingredientCategory;

                if (! $cat) {
                    $mainKey  = '__none__';
                    $mainName = 'Uncategorized';
                    $subKey   = null;
                    $subName  = null;
                } elseif ($cat->parent_id) {
                    // Sub-category — roll up to parent
                    $mainKey  = (string) $cat->parent_id;
                    $mainName = $cat->parent->name ?? 'Unknown';
                    $subKey   = (string) $cat->id;
                    $subName  = $cat->name;
                } else {
                    // Root category
                    $mainKey  = (string) $cat->id;
                    $mainName = $cat->name;
                    $subKey   = null;
                    $subName  = null;
                }

                if (! isset($groups[$mainKey])) {
                    $groups[$mainKey] = [
                        'main_name'    => $mainName,
                        'total_cost'   => 0.0,
                        'sub_breakdown' => [],
                    ];
                }

                $groups[$mainKey]['total_cost'] += $lineCost;

                if ($subKey) {
                    if (! isset($groups[$mainKey]['sub_breakdown'][$subKey])) {
                        $groups[$mainKey]['sub_breakdown'][$subKey] = ['name' => $subName, 'cost' => 0.0];
                    }
                    $groups[$mainKey]['sub_breakdown'][$subKey]['cost'] += $lineCost;
                }
            }

            // Sort: named categories alphabetically, Uncategorized last
            uasort($groups, function ($a, $b) {
                if ($a['main_name'] === 'Uncategorized') return 1;
                if ($b['main_name'] === 'Uncategorized') return -1;
                return strcmp($a['main_name'], $b['main_name']);
            });

            $categoryBreakdown = [
                'date'   => $latestStockTake->stock_take_date,
                'groups' => $groups,
                'total'  => array_sum(array_column($groups, 'total_cost')),
            ];
        }

        return view('livewire.inventory.index', compact(
            'stockTakes', 'wastageRecords', 'staffMealRecords', 'prepItems', 'transfers',
            'monthWastageCost', 'monthStaffMealCost', 'monthStockTakes', 'draftStockTakes', 'totalWastageCost',
            'prepItemCount', 'inTransitCount', 'latestStockTake', 'categoryBreakdown'
        ))->layout('layouts.app', ['title' => 'Inventory']);
    }
}
