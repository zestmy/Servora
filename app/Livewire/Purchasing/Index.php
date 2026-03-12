<?php

namespace App\Livewire\Purchasing;

use App\Models\DeliveryOrder;
use App\Models\GoodsReceivedNote;
use App\Models\Outlet;
use App\Models\PoApprover;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\CsvExportService;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, ScopesToActiveOutlet;

    public string $tab = 'po';

    public string $search = '';
    public string $statusFilter = '';
    public string $supplierFilter = '';
    public string $outletFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    protected $queryString = ['tab'];

    public function updatedTab(): void       { $this->resetPage(); $this->resetFilters(); }
    public function updatedSearch(): void     { $this->resetPage(); }
    public function updatedStatusFilter(): void   { $this->resetPage(); }
    public function updatedSupplierFilter(): void { $this->resetPage(); }
    public function updatedOutletFilter(): void   { $this->resetPage(); }
    public function updatedDateFrom(): void   { $this->resetPage(); }
    public function updatedDateTo(): void     { $this->resetPage(); }

    private function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->supplierFilter = '';
        $this->outletFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    private function isPurchasingRole(): bool
    {
        return Auth::user()->hasRole('Purchasing');
    }

    private function seesAllOutlets(): bool
    {
        return Auth::user()->canViewAllOutlets() || $this->isPurchasingRole();
    }

    /**
     * Get the outlet IDs this user is appointed to approve POs for.
     */
    private function approverOutletIds(): array
    {
        return PoApprover::approverOutletIds(Auth::id());
    }

    /**
     * Whether the current user can approve POs (has any approver appointments).
     */
    private function isAppointed(): bool
    {
        return count($this->approverOutletIds()) > 0;
    }

    // ── PO Actions ──────────────────────────────────────────────────────────

    public function submitPo(int $id): void
    {
        $po = PurchaseOrder::findOrFail($id);
        if ($po->status !== 'draft') return;

        $requiresApproval = Auth::user()->company?->require_po_approval ?? true;

        if ($requiresApproval) {
            $po->update(['status' => 'submitted']);
            session()->flash('success', 'PO submitted for approval.');
        } else {
            $po->update(['status' => 'approved', 'approved_by' => Auth::id()]);
            session()->flash('success', 'PO approved and sent to purchasing team.');
        }
    }

    public function approvePo(int $id): void
    {
        $po = PurchaseOrder::findOrFail($id);

        if ($po->status !== 'submitted') return;

        // Check user is an appointed approver for this PO's outlet
        if (! PoApprover::isApproverFor(Auth::id(), $po->outlet_id)) return;

        $po->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
        ]);
        session()->flash('success', 'PO approved and sent to purchasing team.');
    }

    public function rejectPo(int $id): void
    {
        $po = PurchaseOrder::findOrFail($id);

        if ($po->status !== 'submitted') return;

        if (! PoApprover::isApproverFor(Auth::id(), $po->outlet_id)) return;

        $po->update([
            'status'      => 'cancelled',
            'approved_by' => Auth::id(),
        ]);
        session()->flash('success', 'PO has been rejected.');
    }

    public function cancel(int $id): void
    {
        $po = PurchaseOrder::findOrFail($id);
        if (in_array($po->status, ['draft', 'submitted'])) {
            $po->update(['status' => 'cancelled']);
            session()->flash('success', 'Purchase order cancelled.');
        }
    }

    public function delete(int $id): void
    {
        $po = PurchaseOrder::findOrFail($id);
        if ($po->status === 'draft') {
            $po->delete();
            session()->flash('success', 'Purchase order deleted.');
        }
    }

    // ── CSV Export ───────────────────────────────────────────────────────────

    public function exportCsv()
    {
        $query = PurchaseOrder::with(['supplier', 'outlet', 'lines']);
        $this->applyPoFilters($query);

        $orders = $query->orderByDesc('order_date')->get();

        $headers = ['PO Number', 'Outlet', 'Date', 'Supplier', 'Status', 'Items', 'Total Amount'];
        $rows = $orders->map(fn ($po) => [
            $po->po_number,
            $po->outlet?->name ?? '',
            $po->order_date?->format('Y-m-d') ?? '',
            $po->supplier?->name ?? '',
            ucfirst($po->status),
            $po->lines->count(),
            $po->total_amount,
        ]);

        return CsvExportService::download('purchase-orders.csv', $headers, $rows);
    }

    // ── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        $user = Auth::user();
        $isPurchasing = $this->isPurchasingRole();
        $isAppointed = $this->isAppointed();
        $seesAll = $this->seesAllOutlets();
        $canCreatePo = ! $isPurchasing;
        $approverOutletIds = $this->approverOutletIds();

        $data = match ($this->tab) {
            'do'  => $this->getDoData($seesAll),
            'grn' => $this->getGrnData($seesAll),
            default => $this->getPoData($seesAll),
        };

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $outlets = $seesAll
            ? Outlet::where('company_id', $user->company_id)->where('is_active', true)->orderBy('name')->get()
            : collect();

        // Stats
        $requirePoApproval = $user->company?->require_po_approval ?? true;
        $stats = $this->getStats($isPurchasing, $isAppointed, $approverOutletIds);

        $showPrice = (bool) ($user->company?->show_price_on_do_grn ?? false);

        return view('livewire.purchasing.index', array_merge($data, [
            'suppliers'          => $suppliers,
            'outlets'            => $outlets,
            'isPurchasing'       => $isPurchasing,
            'isAppointed'        => $isAppointed,
            'approverOutletIds'  => $approverOutletIds,
            'seesAllOutlets'     => $seesAll,
            'canCreatePo'        => $canCreatePo,
            'stats'              => $stats,
            'requirePoApproval'  => $requirePoApproval,
            'showPrice'          => $showPrice,
        ]))->layout('layouts.app', ['title' => 'Purchasing']);
    }

    // ── Data Builders ────────────────────────────────────────────────────────

    private function getPoData(bool $seesAll): array
    {
        $query = PurchaseOrder::with(['supplier', 'outlet'])->withCount('lines');
        $this->applyPoFilters($query);

        $orders = $query->orderByDesc('order_date')->orderByDesc('id')->paginate(15);

        return ['orders' => $orders];
    }

    private function getDoData(bool $seesAll): array
    {
        $query = DeliveryOrder::with(['supplier', 'outlet', 'purchaseOrder'])->withCount('lines');

        if ($seesAll) {
            if ($this->outletFilter) {
                $query->where('outlet_id', $this->outletFilter);
            }
        } else {
            $this->scopeByOutlet($query);
        }

        if ($this->search) {
            $query->where('do_number', 'like', '%' . $this->search . '%');
        }
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->dateFrom) {
            $query->where('delivery_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('delivery_date', '<=', $this->dateTo);
        }

        $deliveryOrders = $query->orderByDesc('delivery_date')->orderByDesc('id')->paginate(15);

        return ['deliveryOrders' => $deliveryOrders];
    }

    private function getGrnData(bool $seesAll): array
    {
        $query = GoodsReceivedNote::with(['supplier', 'outlet', 'deliveryOrder'])->withCount('lines');

        if ($seesAll) {
            if ($this->outletFilter) {
                $query->where('outlet_id', $this->outletFilter);
            }
        } else {
            $this->scopeByOutlet($query);
        }

        if ($this->search) {
            $query->where('grn_number', 'like', '%' . $this->search . '%');
        }
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->dateFrom) {
            $query->where('received_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('received_date', '<=', $this->dateTo);
        }

        $grns = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(15);

        return ['grns' => $grns];
    }

    private function applyPoFilters($query): void
    {
        $seesAll = $this->seesAllOutlets();

        if ($seesAll) {
            if ($this->outletFilter) {
                $query->where('outlet_id', $this->outletFilter);
            }
        } else {
            $this->scopeByOutlet($query);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('po_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%' . $this->search . '%'));
            });
        }
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->supplierFilter) {
            $query->where('supplier_id', $this->supplierFilter);
        }
        if ($this->dateFrom) {
            $query->where('order_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('order_date', '<=', $this->dateTo);
        }
    }

    private function getStats(bool $isPurchasing, bool $isAppointed, array $approverOutletIds): array
    {
        if ($isAppointed) {
            $awaitingApproval = PurchaseOrder::where('status', 'submitted')
                ->whereIn('outlet_id', $approverOutletIds)->count();
            $approvedCount = PurchaseOrder::where('status', 'approved')
                ->whereIn('outlet_id', $approverOutletIds)->count();
            $pendingGrnCount = GoodsReceivedNote::where('status', 'pending')
                ->whereIn('outlet_id', $approverOutletIds)->count();

            return [
                ['label' => 'Awaiting Approval', 'value' => $awaitingApproval, 'color' => 'yellow'],
                ['label' => 'Approved (Processing)', 'value' => $approvedCount, 'color' => 'indigo'],
                ['label' => 'Pending Receipt', 'value' => $pendingGrnCount, 'color' => 'blue'],
            ];
        }

        if ($isPurchasing) {
            $approvedCount = PurchaseOrder::where('status', 'approved')->count();
            $pendingDoCount = DeliveryOrder::where('status', 'pending')->count();
            $pendingGrnCount = GoodsReceivedNote::where('status', 'pending')->count();

            return [
                ['label' => 'To Process', 'value' => $approvedCount, 'color' => 'indigo'],
                ['label' => 'Pending Delivery', 'value' => $pendingDoCount, 'color' => 'yellow'],
                ['label' => 'Pending Receipt', 'value' => $pendingGrnCount, 'color' => 'blue'],
            ];
        }

        $draftQ = PurchaseOrder::where('status', 'draft');
        $this->scopeByOutlet($draftQ);
        $draftCount = $draftQ->count();

        $submittedQ = PurchaseOrder::where('status', 'submitted');
        $this->scopeByOutlet($submittedQ);
        $submittedCount = $submittedQ->count();

        $pendingGrnQ = GoodsReceivedNote::where('status', 'pending');
        $this->scopeByOutlet($pendingGrnQ);
        $pendingGrnCount = $pendingGrnQ->count();

        return [
            ['label' => 'Drafts', 'value' => $draftCount, 'color' => 'gray'],
            ['label' => 'Pending Approval', 'value' => $submittedCount, 'color' => 'yellow'],
            ['label' => 'To Receive', 'value' => $pendingGrnCount, 'color' => 'green'],
        ];
    }
}
