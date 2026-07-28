<?php

namespace App\Livewire\Kitchen;

use App\Models\CentralKitchen;
use App\Models\KitchenInventory;
use App\Models\OutletPrepRequest;
use App\Models\OutletTransfer;
use App\Models\OutletTransferLine;
use App\Models\ProductionLog;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    // ── Request Actions ────────────────────────────────────────────────

    public function approveRequest(int $id): void
    {
        $request = OutletPrepRequest::findOrFail($id);
        if (! Auth::user()->canManageKitchen($request->kitchen_id)) {
            session()->flash('error', 'Only kitchen managers can approve requests.');
            return;
        }
        if ($request->status !== 'submitted') return;
        $request->update(['status' => 'approved']);
        session()->flash('success', "Request {$request->request_number} approved.");
    }

    public function fulfillRequest(int $id): void
    {
        $request = OutletPrepRequest::with('lines.ingredient', 'lines.uom')->findOrFail($id);

        // Fulfil moves real stock out of the kitchen and into an outlet, so it
        // is manager-only and the destination outlet must be one this user can
        // actually reach.
        $user = Auth::user();
        if (! $user->canManageKitchen($request->kitchen_id)) {
            session()->flash('error', 'Only kitchen managers can fulfil requests.');
            return;
        }
        if ($request->outlet_id && ! $user->canAccessOutlet((int) $request->outlet_id)) {
            session()->flash('error', 'You do not have access to the destination outlet.');
            return;
        }
        if (! in_array($request->status, ['approved', 'submitted'])) return;

        $kitchenId = $request->kitchen_id;
        $kitchen = CentralKitchen::find($kitchenId);
        $kitchenOutletId = $kitchen?->outlet_id;

        if (! $kitchenOutletId) {
            session()->flash('error', 'Kitchen has no linked outlet for transfers.');
            return;
        }

        DB::transaction(function () use ($request, $kitchenId, $kitchenOutletId) {
            $userId = Auth::id();
            $companyId = $request->company_id;

            // Create transfer from kitchen to requesting outlet
            $transferNumber = 'KF-' . now()->format('Ymd') . '-' . substr($request->request_number, -3);
            $transfer = OutletTransfer::create([
                'company_id'      => $companyId,
                'from_outlet_id'  => $kitchenOutletId,
                'to_outlet_id'    => $request->outlet_id,
                'transfer_number' => $transferNumber,
                'status'          => 'in_transit',
                'transfer_date'   => now()->toDateString(),
                'notes'           => "Fulfilled from kitchen for {$request->request_number}",
                'created_by'      => $userId,
            ]);

            foreach ($request->lines as $line) {
                $qty = floatval($line->requested_quantity);
                if ($qty <= 0 || ! $line->ingredient_id) continue;

                // Deduct from kitchen inventory
                KitchenInventory::deductStock($kitchenId, $line->ingredient_id, $qty);

                // Add to transfer
                OutletTransferLine::create([
                    'outlet_transfer_id' => $transfer->id,
                    'ingredient_id'      => $line->ingredient_id,
                    'quantity'           => $qty,
                    'uom_id'            => $line->uom_id,
                    'unit_cost'         => floatval($line->ingredient?->current_cost ?? 0),
                ]);

                // Mark line as fulfilled
                $line->update(['fulfilled_quantity' => $qty]);
            }

            $request->update(['status' => 'fulfilled']);
        });

        session()->flash('success', "Request {$request->request_number} fulfilled. Transfer created to {$request->outlet?->name}.");
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
                'label' => 'Requests waiting on you',
                'value' => $k(OutletPrepRequest::whereIn('status', ['submitted', 'approved']))->count(),
                'color' => 'yellow',
                'hint'  => 'Outlets waiting for items to be approved or sent',
                'tab'   => 'requests', 'status' => '',
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

    private function getRequestsData(): array
    {
        $query = OutletPrepRequest::with(['outlet', 'kitchen', 'createdBy'])->withCount('lines');
        if ($this->statusFilter) $query->where('status', $this->statusFilter);
        if ($this->kitchenFilter) $query->where('kitchen_id', $this->kitchenFilter);
        return ['requests' => $query->orderByDesc('needed_date')->orderByDesc('id')->paginate(15)];
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
            'requests'  => $this->getRequestsData(),
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
