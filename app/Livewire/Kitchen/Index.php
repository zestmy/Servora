<?php

namespace App\Livewire\Kitchen;

use App\Models\CentralKitchen;
use App\Models\OutletPrepRequest;
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

    protected $queryString = ['tab'];

    public function updatedTab(): void       { $this->resetPage(); $this->resetFilters(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedDateFrom(): void  { $this->resetPage(); }
    public function updatedDateTo(): void    { $this->resetPage(); }

    private function resetFilters(): void
    {
        $this->statusFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    // ── Order Actions ────────────────────────────────────────────────────────

    public function scheduleOrder(int $id): void
    {
        $order = ProductionOrder::findOrFail($id);
        if ($order->status !== 'draft') return;

        $order->update(['status' => 'scheduled']);
        session()->flash('success', "Order {$order->order_number} scheduled.");
    }

    public function cancelOrder(int $id): void
    {
        $order = ProductionOrder::findOrFail($id);
        if (! in_array($order->status, ['draft', 'scheduled'])) return;

        $order->update(['status' => 'cancelled']);
        session()->flash('success', "Order {$order->order_number} cancelled.");
    }

    // ── Request Actions ──────────────────────────────────────────────────────

    public function approveRequest(int $id): void
    {
        $request = OutletPrepRequest::findOrFail($id);
        if ($request->status !== 'submitted') return;

        $request->update(['status' => 'approved']);
        session()->flash('success', "Request {$request->request_number} approved.");
    }

    public function fulfillRequest(int $id): void
    {
        $request = OutletPrepRequest::findOrFail($id);
        if (! in_array($request->status, ['approved', 'submitted'])) return;

        $request->update(['status' => 'fulfilled']);
        session()->flash('success', "Request {$request->request_number} fulfilled.");
    }

    // ── Stats ────────────────────────────────────────────────────────────────

    private function getStats(): array
    {
        $today = now()->toDateString();

        $todayOrders = ProductionOrder::whereIn('status', ['scheduled', 'in_progress'])
            ->whereDate('production_date', $today)
            ->count();

        $pendingRequests = OutletPrepRequest::where('status', 'submitted')->count();

        $completedToday = ProductionOrder::where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->count();

        return [
            ['label' => "Today's Orders", 'value' => $todayOrders, 'color' => 'indigo'],
            ['label' => 'Pending Requests', 'value' => $pendingRequests, 'color' => 'yellow'],
            ['label' => 'Completed Today', 'value' => $completedToday, 'color' => 'green'],
        ];
    }

    // ── Data Builders ────────────────────────────────────────────────────────

    private function getOrdersData(): array
    {
        $query = ProductionOrder::with(['kitchen', 'createdBy'])->withCount('lines');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->dateFrom) {
            $query->whereDate('production_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('production_date', '<=', $this->dateTo);
        }

        return ['orders' => $query->orderByDesc('production_date')->orderByDesc('id')->paginate(15)];
    }

    private function getRequestsData(): array
    {
        $query = OutletPrepRequest::with(['outlet', 'kitchen'])->withCount('lines');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return ['requests' => $query->orderByDesc('needed_date')->orderByDesc('id')->paginate(15)];
    }

    private function getLogsData(): array
    {
        $query = ProductionLog::with(['recipe', 'producedBy']);

        return ['logs' => $query->orderByDesc('produced_at')->orderByDesc('id')->paginate(15)];
    }

    // ── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        $data = match ($this->tab) {
            'requests' => $this->getRequestsData(),
            'logs'     => $this->getLogsData(),
            default    => $this->getOrdersData(),
        };

        return view('livewire.kitchen.index', array_merge($data, [
            'stats' => $this->getStats(),
        ]))->layout('layouts.app', ['title' => 'Kitchen']);
    }
}
