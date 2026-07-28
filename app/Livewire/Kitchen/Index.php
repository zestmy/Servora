<?php

namespace App\Livewire\Kitchen;

use App\Models\CentralKitchen;
use App\Models\KitchenInventory;
use App\Models\ProductionLog;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $tab = 'orders';

    public string $statusFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?int $kitchenFilter = null;

    protected $queryString = ['tab'];

    /** The tabs this screen still has. */
    private const TABS = ['orders', 'inventory', 'logs'];

    public function mount(): void
    {
        // ?tab= comes from the URL, so a bookmark or a stale link (the retired
        // prep-requests tab, for one) can name a tab that no longer exists —
        // which would render an empty page. Fall back to the default.
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'orders';
        }
    }

    public function updatedTab(): void         { $this->resetPage(); $this->resetFilters(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedDateFrom(): void    { $this->resetPage(); }
    public function updatedDateTo(): void      { $this->resetPage(); }
    public function updatedKitchenFilter(): void { $this->resetPage(); }

    private function resetFilters(): void
    {
        $this->statusFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    // ── Order Actions ──────────────────────────────────────────────────

    public function scheduleOrder(int $id): void
    {
        $order = ProductionOrder::findOrFail($id);
        if (! Auth::user()->canRunProduction($order->kitchen_id)) {
            session()->flash('error', 'Only kitchen managers and chefs can schedule production.');
            return;
        }
        if ($order->status !== 'draft') return;
        $order->update(['status' => 'scheduled']);
        session()->flash('success', "Order {$order->order_number} scheduled.");
    }

    public function cancelOrder(int $id): void
    {
        $order = ProductionOrder::findOrFail($id);
        if (! Auth::user()->canManageKitchen($order->kitchen_id)) {
            session()->flash('error', 'Only kitchen managers can cancel an order.');
            return;
        }
        if (! in_array($order->status, ['draft', 'scheduled'])) return;
        $order->update(['status' => 'cancelled']);
        session()->flash('success', "Order {$order->order_number} cancelled.");
    }

    // ── Stats ──────────────────────────────────────────────────────────

    /**
     * Headline counts. These honour the kitchen filter — a number that ignores
     * the filter above it reads as a bug when the list below disagrees. Each
     * one is a shortcut into the tab and status that produced it.
     */
    private function getStats(): array
    {
        $today = now()->toDateString();
        $k = fn ($q) => $this->kitchenFilter ? $q->where('kitchen_id', $this->kitchenFilter) : $q;

        return [
            [
                'label' => "To make today",
                'value' => $k(ProductionOrder::whereIn('status', ['scheduled', 'in_progress'])->whereDate('production_date', $today))->count(),
                'color' => 'indigo',
                'hint'  => 'Scheduled or in progress, due today',
                'tab'   => 'orders', 'status' => 'scheduled',
            ],
            [
                'label' => 'In progress',
                'value' => $k(ProductionOrder::where('status', 'in_progress'))->count(),
                'color' => 'yellow',
                'hint'  => 'Batches started but not yet completed',
                'tab'   => 'orders', 'status' => 'in_progress',
            ],
            [
                'label' => 'Completed today',
                'value' => $k(ProductionOrder::where('status', 'completed')->whereDate('completed_at', $today))->count(),
                'color' => 'green',
                'hint'  => 'Batches finished and added to kitchen stock',
                'tab'   => 'orders', 'status' => 'completed',
            ],
        ];
    }

    /** Jump to the tab and status behind a headline number. */
    public function openStat(string $tab, string $status = ''): void
    {
        $this->tab = $tab;
        $this->resetFilters();
        $this->statusFilter = $status;
        $this->resetPage();
    }

    // ── Data Builders ──────────────────────────────────────────────────

    private function getOrdersData(): array
    {
        $query = ProductionOrder::with(['kitchen', 'createdBy'])->withCount('lines');
        if ($this->statusFilter) $query->where('status', $this->statusFilter);
        if ($this->dateFrom) $query->whereDate('production_date', '>=', $this->dateFrom);
        if ($this->dateTo) $query->whereDate('production_date', '<=', $this->dateTo);
        if ($this->kitchenFilter) $query->where('kitchen_id', $this->kitchenFilter);
        return ['orders' => $query->orderByDesc('production_date')->orderByDesc('id')->paginate(15)];
    }

    private function getLogsData(): array
    {
        $query = ProductionLog::with(['recipe', 'productionRecipe', 'producedBy']);
        if ($this->dateFrom) $query->whereDate('produced_at', '>=', $this->dateFrom);
        if ($this->dateTo) $query->whereDate('produced_at', '<=', $this->dateTo);
        return ['logs' => $query->orderByDesc('produced_at')->paginate(15)];
    }

    private function getInventoryData(): array
    {
        $query = KitchenInventory::with(['ingredient', 'uom', 'kitchen'])
            ->where('quantity_on_hand', '>', 0);
        if ($this->kitchenFilter) $query->where('kitchen_id', $this->kitchenFilter);
        return ['inventory' => $query->orderBy('kitchen_id')->paginate(20)];
    }

    // ── Render ─────────────────────────────────────────────────────────

    public function render()
    {
        $data = match ($this->tab) {
            'inventory' => $this->getInventoryData(),
            'logs'      => $this->getLogsData(),
            default     => $this->getOrdersData(),
        };

        $kitchens = CentralKitchen::active()->orderBy('name')->get();

        // Row actions are hidden rather than shown-and-refused; the component
        // methods re-check, so this is presentation only.
        $user = Auth::user();
        $activeKitchenId = $this->kitchenFilter ?: $user->activeKitchen()?->id;

        return view('livewire.kitchen.index', array_merge($data, [
            'stats'      => $this->getStats(),
            'kitchens'   => $kitchens,
            'canManage'  => $user->canManageKitchen($activeKitchenId),
            'canProduce' => $user->canRunProduction($activeKitchenId),
        ]))->layout('layouts.kitchen', ['title' => 'Kitchen']);
    }
}
