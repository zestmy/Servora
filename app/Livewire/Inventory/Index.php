<?php

namespace App\Livewire\Inventory;

use App\Models\Department;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use App\Models\PurchaseCapture;
use App\Models\Recipe;
use App\Models\StaffMealRecord;
use App\Models\StockTake;
use App\Models\Supplier;
use App\Models\WastageRecord;
use App\Traits\ScopesToActiveOutlet;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, ScopesToActiveOutlet, \App\Traits\HasQuickDateRanges;

    /**
     * What each tab is made of.
     *
     * The five tabs were five near-identical blocks of query building that had
     * drifted apart — department filtered on wastage only, though four of the
     * five tables carry the column and every form fills it; supplier filtered
     * nowhere, though purchases store it and use it. Describing the differences
     * as data instead means a filter is written once and every tab that can
     * answer it does.
     */
    private const TABS = [
        'stock-takes' => [
            'label'  => 'Stock Takes',
            'model'  => StockTake::class,
            'date'   => 'stock_take_date',
            'amount' => 'total_stock_cost',
            'money'  => 'Counted value',
            'search' => ['reference_number'],
            'dept'   => true,
            'supplier' => false,
            'status' => false,
        ],
        'wastage' => [
            'label'  => 'Wastage',
            'model'  => WastageRecord::class,
            'date'   => 'wastage_date',
            'amount' => 'total_cost',
            'money'  => 'Wastage cost',
            'search' => ['reference_number'],
            'dept'   => true,
            'supplier' => false,
            'status' => false,
        ],
        'staff-meals' => [
            'label'  => 'Staff Meals',
            'model'  => StaffMealRecord::class,
            'date'   => 'meal_date',
            'amount' => 'total_cost',
            'money'  => 'Staff meal cost',
            'search' => ['reference_number'],
            'dept'   => true,
            'supplier' => false,
            'status' => false,
        ],
        'transfers' => [
            'label'  => 'Transfers',
            'model'  => OutletTransfer::class,
            'date'   => 'transfer_date',
            'amount' => null,
            'money'  => null,
            'search' => ['transfer_number'],
            'dept'   => false,
            'supplier' => false,
            'status' => true,
        ],
        'purchases' => [
            'label'  => 'Purchases',
            'model'  => PurchaseCapture::class,
            'date'   => 'purchase_date',
            'amount' => 'amount',
            'money'  => 'Purchase value',
            'search' => ['reference_number', 'supplier_name'],
            'dept'   => true,
            'supplier' => true,
            'status' => false,
        ],
    ];

    #[Url]
    public string $tab = 'stock-takes';

    #[Url]
    public string $search = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $outletFilter = '';

    #[Url]
    public string $departmentFilter = '';

    #[Url]
    public string $supplierFilter = '';

    public function mount(): void
    {
        $tab = request('tab');
        if (isset(self::TABS[$tab])) {
            $this->tab = $tab;
        }

        if ($this->dateFrom === '' && $this->dateTo === '') {
            $this->applyQuickRange($this->quickRange ?: 'last_30');
        }
    }

    /**
     * Switching tab keeps the range and the outlet, drops the rest.
     *
     * It used to clear the dates too, which meant answering "what did we waste
     * last week" and then having to re-say "last week" to ask the same question
     * of purchases. Filters that cannot apply to the new tab are cleared,
     * because a supplier filter silently surviving onto Wastage is a filter
     * doing nothing while looking like it is doing something.
     */
    public function updatedTab(): void
    {
        $this->resetPage();
        $this->search = '';

        if (! $this->tabConfig()['dept']) {
            $this->departmentFilter = '';
        }

        if (! $this->tabConfig()['supplier']) {
            $this->supplierFilter = '';
        }

        if (! $this->tabConfig()['status']) {
            $this->statusFilter = '';
        }
    }

    public function updatedStatusFilter(): void     { $this->resetPage(); }
    public function updatedOutletFilter(): void     { $this->resetPage(); }
    public function updatedDepartmentFilter(): void { $this->resetPage(); }
    public function updatedSupplierFilter(): void   { $this->resetPage(); }
    public function updatedSearch(): void           { $this->resetPage(); }
    public function updatedDateFrom(): void         { $this->quickRange = ''; $this->resetPage(); }
    public function updatedDateTo(): void           { $this->quickRange = ''; $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['search', 'outletFilter', 'departmentFilter', 'supplierFilter', 'statusFilter']);
        $this->setQuickRange('last_30');
    }

    /** @return array<string, mixed> */
    private function tabConfig(?string $tab = null): array
    {
        return self::TABS[$tab ?? $this->tab] ?? self::TABS['stock-takes'];
    }

    /**
     * Deleting is now gated per document type.
     *
     * It used to be one `inventory.delete` for the whole module — and, worse, only
     * deleteStockTake() and deleteTransfer() actually consulted it. Wastage, staff meals,
     * captured purchases and prep items had NO check at all: anyone who could open
     * Inventory could delete them outright. All six are guarded now, and each type is
     * revocable on its own, so letting someone void a mistaken wastage line no longer
     * also lets them reverse a completed stock take.
     */
    private function canDeleteType(string $type): bool
    {
        // Each arm calls canDo() with a literal rather than interpolating
        // "inventory.{$type}.delete", because PermissionRegistryTest greps the source for
        // enforcement — a permission name assembled at runtime is invisible to it, and a
        // permission the drift check cannot see is one that can silently rot. The test
        // caught this method twice while it was being written.
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return match ($type) {
            'stock_takes' => $user->canDo('inventory.stock_takes.delete'),
            'wastage'     => $user->canDo('inventory.wastage.delete'),
            'transfers'   => $user->canDo('inventory.transfers.delete'),
            'staff_meals' => $user->canDo('inventory.staff_meals.delete'),
            'prep_items'  => $user->canDo('inventory.prep_items.delete'),
            'purchases'   => $user->canDo('inventory.purchases.delete'),
        };
    }

    public function deleteStockTake(int $id): void
    {
        $stockTake = StockTake::findOrFail($id);

        // Drafts stay freely deletable — nothing has hit stock yet. Reversing a completed
        // count is the part that needs the ability.
        if ($stockTake->status !== 'draft' && ! $this->canDeleteType('stock_takes')) {
            session()->flash('error', 'You do not have permission to delete a completed stock take.');
            return;
        }

        $stockTake->delete();
        session()->flash('success', 'Stock take deleted.');
    }

    public function deleteWastage(int $id): void
    {
        if (! $this->canDeleteType('wastage')) {
            session()->flash('error', 'You do not have permission to delete wastage records.');
            return;
        }

        WastageRecord::findOrFail($id)->delete();
        session()->flash('success', 'Wastage record deleted.');
    }

    public function deleteStaffMeal(int $id): void
    {
        if (! $this->canDeleteType('staff_meals')) {
            session()->flash('error', 'You do not have permission to delete staff meal records.');
            return;
        }

        StaffMealRecord::findOrFail($id)->delete();
        session()->flash('success', 'Staff meal record deleted.');
    }

    public function deleteTransfer(int $id): void
    {
        $transfer = OutletTransfer::findOrFail($id);
        if ($transfer->status !== 'draft' && ! $this->canDeleteType('transfers')) {
            session()->flash('error', 'Only draft transfers can be deleted without the delete permission.');
            return;
        }
        $transfer->delete();
        session()->flash('success', 'Transfer deleted.');
    }

    public function deletePurchase(int $id): void
    {
        if (! $this->canDeleteType('purchases')) {
            session()->flash('error', 'You do not have permission to delete captured purchases.');
            return;
        }

        PurchaseCapture::findOrFail($id)->delete();
        session()->flash('success', 'Purchase deleted.');
    }

    public function deletePrepItem(int $recipeId): void
    {
        if (! $this->canDeleteType('prep_items')) {
            session()->flash('error', 'You do not have permission to delete prep items.');
            return;
        }

        $recipe = Recipe::with('ingredient')->findOrFail($recipeId);
        // Also soft-delete the synced ingredient record
        $recipe->ingredient?->delete();
        $recipe->delete();
        session()->flash('success', 'Prep item deleted.');
    }

    /**
     * The active tab's records, filtered.
     *
     * Every filter is applied in ONE place, so the stats above the table cannot
     * disagree with the table — which they did: the cards were hardcoded to
     * whereMonth(now()) and ignored the date range, the outlet, the department
     * and the search, so narrowing the table to last week left the cards
     * answering about the month.
     */
    private function filtered(?string $from = null, ?string $to = null): Builder
    {
        $config = $this->tabConfig();
        $from ??= $this->dateFrom;
        $to   ??= $this->dateTo;
        $query  = $config['model']::query();

        // Transfers name two outlets, so they cannot use the outlet_id scope:
        // a transfer belongs to the branch that sent it AND the one receiving.
        if ($this->tab === 'transfers') {
            $ids = $this->selectedOutletId($this->outletFilter)
                ? [$this->selectedOutletId($this->outletFilter)]
                : $this->availableOutletIds();

            if (! empty($ids)) {
                $query->where(fn ($q) => $q
                    ->whereIn('from_outlet_id', $ids)
                    ->orWhereIn('to_outlet_id', $ids));
            }
        } else {
            $this->scopeByOutletFilter($query, $this->outletFilter);
        }

        if ($config['dept'] && $this->departmentFilter !== '') {
            // "No department" is offered explicitly: records predating the
            // column would otherwise be unreachable by any filter value.
            $this->departmentFilter === 'none'
                ? $query->whereNull('department_id')
                : $query->where('department_id', (int) $this->departmentFilter);
        }

        if ($config['supplier'] && $this->supplierFilter !== '') {
            $query->where('supplier_id', (int) $this->supplierFilter);
        }

        if ($config['status'] && $this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search !== '') {
            $query->where(function ($q) use ($config) {
                foreach ($config['search'] as $i => $column) {
                    $i === 0
                        ? $q->where($column, 'like', '%' . $this->search . '%')
                        : $q->orWhere($column, 'like', '%' . $this->search . '%');
                }
            });
        }

        if ($from !== '') {
            $query->whereDate($config['date'], '>=', $from);
        }

        if ($to !== '') {
            $query->whereDate($config['date'], '<=', $to);
        }

        return $query;
    }

    /**
     * The same question asked of the period before this one.
     *
     * A number on its own says nothing — RM 400 of wastage is good or terrible
     * depending on last week. Only computable when both ends of the range are
     * known, so a hand-typed open range simply gets no comparison rather than
     * a made-up one.
     */
    private function previousPeriod(): ?array
    {
        if ($this->dateFrom === '' || $this->dateTo === '') {
            return null;
        }

        $from = Carbon::parse($this->dateFrom);
        $to   = Carbon::parse($this->dateTo);
        $days = $from->diffInDays($to) + 1;

        return [
            $from->copy()->subDays($days)->toDateString(),
            $from->copy()->subDay()->toDateString(),
        ];
    }

    /**
     * Cards about the tab you are looking at, under the filters you have set.
     *
     * Five fixed cards used to describe five different tabs at once — three of
     * them about tabs this company has never used, so most of the dashboard read
     * as zeros and dashes regardless of what you were doing.
     *
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $config = $this->tabConfig();

        $count = (clone $this->filtered())->count();
        $value = $config['amount'] ? (float) (clone $this->filtered())->sum($config['amount']) : null;

        $previousValue = null;
        $previousCount = null;

        if ($period = $this->previousPeriod()) {
            $previousCount = $this->filtered(...$period)->count();
            $previousValue = $config['amount']
                ? (float) $this->filtered(...$period)->sum($config['amount'])
                : null;
        }

        return [
            'label'         => $config['label'],
            'moneyLabel'    => $config['money'],
            'count'         => $count,
            'value'         => $value,
            'previousCount' => $previousCount,
            'previousValue' => $previousValue,
            'rangeLabel'    => $this->rangeLabel(),
        ];
    }

    /**
     * Third card: the one thing worth knowing about THIS tab.
     *
     * @return array{label: string, value: string, tone: string}|null
     */
    private function highlight(): ?array
    {
        if ($this->tab === 'stock-takes') {
            $drafts = (clone $this->filtered())->where('status', 'draft')->count();

            return [
                'label' => 'Drafts pending',
                'value' => (string) $drafts,
                'tone'  => $drafts > 0 ? 'warning' : 'muted',
            ];
        }

        if ($this->tab === 'transfers') {
            $inTransit = (clone $this->filtered())->where('status', 'in_transit')->count();

            return [
                'label' => 'In transit',
                'value' => (string) $inTransit,
                'tone'  => $inTransit > 0 ? 'warning' : 'muted',
            ];
        }

        if ($this->tab === 'purchases') {
            $top = (clone $this->filtered())
                ->selectRaw('supplier_name, SUM(amount) AS spend')
                ->whereNotNull('supplier_name')
                ->groupBy('supplier_name')
                ->orderByDesc('spend')
                ->first();

            return [
                'label' => 'Biggest supplier',
                'value' => $top?->supplier_name ?? '—',
                'tone'  => 'muted',
            ];
        }

        if (in_array($this->tab, ['wastage', 'staff-meals'], true)) {
            $top = (clone $this->filtered())
                ->selectRaw('department_id, SUM(total_cost) AS spend')
                ->whereNotNull('department_id')
                ->groupBy('department_id')
                ->orderByDesc('spend')
                ->first();

            return [
                'label' => $this->tab === 'wastage' ? 'Most wastage' : 'Most meals',
                'value' => $top ? (Department::find($top->department_id)?->name ?? '—') : '—',
                'tone'  => 'muted',
            ];
        }

        return null;
    }

    /**
     * Stock value split by category, from the most recent completed count.
     *
     * Only built on the Stock Takes tab. It loads every line of that count with
     * a three-deep eager load, and it used to run on every render of every tab —
     * including once per keystroke behind the 300ms search debounce.
     */
    private function categoryBreakdown(?StockTake $latest): ?array
    {
        if (! $latest || $this->tab !== 'stock-takes') {
            return null;
        }

        $groups = [];

        foreach ($latest->lines()->with(['ingredient.ingredientCategory.parent'])->get() as $line) {
            $lineCost = (float) $line->actual_quantity * (float) $line->unit_cost;
            $cat      = $line->ingredient?->ingredientCategory;

            if (! $cat) {
                [$mainKey, $mainName, $subKey, $subName] = ['__none__', 'Uncategorized', null, null];
            } elseif ($cat->parent_id) {
                [$mainKey, $mainName, $subKey, $subName] = [(string) $cat->parent_id, $cat->parent->name ?? 'Unknown', (string) $cat->id, $cat->name];
            } else {
                [$mainKey, $mainName, $subKey, $subName] = [(string) $cat->id, $cat->name, null, null];
            }

            $groups[$mainKey] ??= ['main_name' => $mainName, 'total_cost' => 0.0, 'sub_breakdown' => []];
            $groups[$mainKey]['total_cost'] += $lineCost;

            if ($subKey) {
                $groups[$mainKey]['sub_breakdown'][$subKey] ??= ['name' => $subName, 'cost' => 0.0];
                $groups[$mainKey]['sub_breakdown'][$subKey]['cost'] += $lineCost;
            }
        }

        uasort($groups, function ($a, $b) {
            if ($a['main_name'] === 'Uncategorized') return 1;
            if ($b['main_name'] === 'Uncategorized') return -1;

            return strcmp($a['main_name'], $b['main_name']);
        });

        return [
            'date'   => $latest->stock_take_date,
            'groups' => $groups,
            'total'  => array_sum(array_column($groups, 'total_cost')),
        ];
    }

    public function render()
    {
        $config = $this->tabConfig();

        $records = $this->filtered()
            ->when($this->tab === 'wastage', fn ($q) => $q->with(['department:id,name', 'lines:id,wastage_record_id,reason']))
            ->when($this->tab === 'transfers', fn ($q) => $q->with(['fromOutlet', 'toOutlet']))
            ->when($this->tab === 'purchases', fn ($q) => $q->with(['department', 'supplier']))
            ->when($this->tab !== 'purchases', fn ($q) => $q->withCount('lines'))
            ->orderByDesc($config['date'])
            ->orderByDesc('id')
            ->paginate(15);

        $latestStQ = StockTake::where('status', 'completed');
        $this->scopeByOutletFilter($latestStQ, $this->outletFilter);
        $latestStockTake = $this->tab === 'stock-takes'
            ? $latestStQ->orderByDesc('stock_take_date')->orderByDesc('id')->first(['id', 'stock_take_date', 'total_stock_cost'])
            : null;

        // Transfers carry no department and purchases are the only thing with a
        // supplier, so each list is fetched only where it can be offered.
        $departments = $config['dept'] ? Department::orderBy('name')->get(['id', 'name']) : collect();
        $suppliers   = $config['supplier'] ? Supplier::orderBy('name')->get(['id', 'name']) : collect();

        $canDelete = collect(['stock_takes', 'wastage', 'transfers', 'staff_meals', 'prep_items', 'purchases'])
            ->mapWithKeys(fn (string $t) => [$t => $this->canDeleteType($t)])
            ->all();

        return view('livewire.inventory.index', [
            'records'           => $records,
            'stats'             => $this->stats(),
            'highlight'         => $this->highlight(),
            'latestStockTake'   => $latestStockTake,
            'categoryBreakdown' => $this->categoryBreakdown($latestStockTake),
            'filterOutlets'     => $this->filterableOutlets(),
            'departments'       => $departments,
            'suppliers'         => $suppliers,
            'tabs'              => collect(self::TABS)->map(fn ($c) => $c['label'])->all(),
            'tabConfig'         => $config,
            'canDelete'         => $canDelete,
            // Drafts stay deletable for stock takes and transfers regardless.
            'canDeleteRecords'  => $canDelete['stock_takes'],
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Stocks Management']);
    }
}
