<?php

namespace App\Livewire\Inventory;

use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use App\Models\PurchaseCapture;
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

    public string $tab          = 'stock-takes';
    public string $search       = '';
    public string $dateFrom     = '';
    public string $dateTo       = '';
    public string $statusFilter = '';
    public string $outletFilter = '';

    public function mount(): void
    {
        $tab = request('tab');
        if (in_array($tab, ['stock-takes', 'wastage', 'staff-meals', 'transfers', 'purchases'], true)) {
            $this->tab = $tab;
        }
    }

    public function updatedTab(): void      { $this->resetPage(); $this->search = ''; $this->dateFrom = ''; $this->dateTo = ''; $this->statusFilter = ''; }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedOutletFilter(): void { $this->resetPage(); }
    public function updatedSearch(): void   { $this->resetPage(); }
    public function updatedDateFrom(): void { $this->resetPage(); }
    public function updatedDateTo(): void   { $this->resetPage(); }

    /** Users with the Delete Record capability (or system admins) may remove finalised records. */
    private function canDeleteRecords(): bool
    {
        $user = auth()->user();
        return $user->isSystemRole() || $user->hasCapability('can_delete_records');
    }

    public function deleteStockTake(int $id): void
    {
        $stockTake = StockTake::findOrFail($id);

        // Drafts are freely deletable; completed stock takes require the
        // Delete Record capability to reverse.
        if ($stockTake->status !== 'draft' && ! $this->canDeleteRecords()) {
            session()->flash('error', 'You do not have permission to delete a completed stock take.');
            return;
        }

        $stockTake->delete();
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
        if ($transfer->status !== 'draft' && ! $this->canDeleteRecords()) {
            session()->flash('error', 'Only draft transfers can be deleted without the Delete Record permission.');
            return;
        }
        $transfer->delete();
        session()->flash('success', 'Transfer deleted.');
    }

    public function deletePurchase(int $id): void
    {
        PurchaseCapture::findOrFail($id)->delete();
        session()->flash('success', 'Purchase deleted.');
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
        $this->scopeByOutletFilter($stockQuery, $this->outletFilter);

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
        $this->scopeByOutletFilter($wastageQuery, $this->outletFilter);

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
        $this->scopeByOutletFilter($staffMealQuery, $this->outletFilter);

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

        // ── Transfers ────────────────────────────────────────────────────
        // Transfers reference two outlets (from/to), so they can't use the
        // outlet_id scope. Bound them to the user's accessible outlets, then
        // narrow to the selected outlet when the filter is applied.
        $selectedOutletId = $this->selectedOutletId($this->outletFilter);
        $transferOutletIds = $selectedOutletId ? [$selectedOutletId] : $this->availableOutletIds();

        $transferQuery = OutletTransfer::withCount('lines')
            ->with(['fromOutlet', 'toOutlet']);

        if (! empty($transferOutletIds)) {
            $transferQuery->where(function ($q) use ($transferOutletIds) {
                $q->whereIn('from_outlet_id', $transferOutletIds)
                  ->orWhereIn('to_outlet_id', $transferOutletIds);
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

        // ── Purchases ─────────────────────────────────────────────────────
        $purchaseQuery = PurchaseCapture::with(['department', 'supplier']);
        $this->scopeByOutletFilter($purchaseQuery, $this->outletFilter);

        if ($this->search && $this->tab === 'purchases') {
            $purchaseQuery->where(function ($q) {
                $q->where('reference_number', 'like', '%' . $this->search . '%')
                  ->orWhere('supplier_name', 'like', '%' . $this->search . '%');
            });
        }
        if ($this->dateFrom && $this->tab === 'purchases') {
            $purchaseQuery->where('purchase_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo && $this->tab === 'purchases') {
            $purchaseQuery->where('purchase_date', '<=', $this->dateTo);
        }

        $purchases = $this->tab === 'purchases'
            ? $purchaseQuery->orderByDesc('purchase_date')->orderByDesc('id')->paginate(15)
            : collect();

        // ── Stats ─────────────────────────────────────────────────────────
        $wastageStatQ = WastageRecord::whereMonth('wastage_date', now()->month)
            ->whereYear('wastage_date', now()->year);
        $this->scopeByOutletFilter($wastageStatQ, $this->outletFilter);
        $monthWastageCost = $wastageStatQ->sum('total_cost');

        $stStatQ = StockTake::whereMonth('stock_take_date', now()->month)
            ->whereYear('stock_take_date', now()->year);
        $this->scopeByOutletFilter($stStatQ, $this->outletFilter);
        $monthStockTakes = $stStatQ->count();

        $draftQ = StockTake::where('status', 'draft');
        $this->scopeByOutletFilter($draftQ, $this->outletFilter);
        $draftStockTakes = $draftQ->count();

        $totalWastQ = WastageRecord::query();
        $this->scopeByOutletFilter($totalWastQ, $this->outletFilter);
        $totalWastageCost = $totalWastQ->sum('total_cost');

        $staffMealStatQ = StaffMealRecord::whereMonth('meal_date', now()->month)
            ->whereYear('meal_date', now()->year);
        $this->scopeByOutletFilter($staffMealStatQ, $this->outletFilter);
        $monthStaffMealCost = $staffMealStatQ->sum('total_cost');

        $purchaseStatQ = PurchaseCapture::whereMonth('purchase_date', now()->month)
            ->whereYear('purchase_date', now()->year);
        $this->scopeByOutletFilter($purchaseStatQ, $this->outletFilter);
        $monthPurchaseAmount = $purchaseStatQ->sum('amount');

        // prepItemCount removed — prep items now under Recipes tab

        $inTransitQ = OutletTransfer::where('status', 'in_transit');
        if (! empty($transferOutletIds)) {
            $inTransitQ->where(function ($q) use ($transferOutletIds) {
                $q->whereIn('from_outlet_id', $transferOutletIds)->orWhereIn('to_outlet_id', $transferOutletIds);
            });
        }
        $inTransitCount = $inTransitQ->count();

        $latestStQ = StockTake::where('status', 'completed');
        $this->scopeByOutletFilter($latestStQ, $this->outletFilter);
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

        $filterOutlets = $this->filterableOutlets();
        $canDeleteRecords = $this->canDeleteRecords();

        return view('livewire.inventory.index', compact(
            'stockTakes', 'wastageRecords', 'staffMealRecords', 'transfers', 'purchases',
            'monthWastageCost', 'monthStaffMealCost', 'monthStockTakes', 'draftStockTakes', 'totalWastageCost',
            'monthPurchaseAmount', 'inTransitCount', 'latestStockTake', 'categoryBreakdown', 'filterOutlets', 'canDeleteRecords'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Inventory']);
    }
}
