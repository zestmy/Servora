<?php

namespace App\Livewire\Purchasing;

use App\Models\DeliveryOrder;
use App\Models\GoodsReceivedNote;
use App\Models\Outlet;
use App\Models\PoApprover;
use App\Models\PrApprover;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRecord;
use App\Models\PurchaseRequest;
use App\Models\StockTransferOrder;
use App\Models\Supplier;
use App\Services\CsvExportService;
use App\Services\PoEmailService;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
    public string $rejectReason = '';
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
        return Auth::user()->hasPermissionTo('purchasing.view');
    }

    private function seesAllOutlets(): bool
    {
        return Auth::user()->can_view_all_outlets || Auth::user()->isSystemRole() || $this->isPurchasingRole();
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

    /**
     * Get the user's approver assignments as [{outlet_id, department_id}].
     */
    private function approverAssignments(): array
    {
        return PoApprover::where('user_id', Auth::id())
            ->select('outlet_id', 'department_id')
            ->get()
            ->toArray();
    }

    private function isCpuMode(): bool
    {
        return Auth::user()->company?->ordering_mode === 'cpu';
    }

    private function isCpuUser(): bool
    {
        return DB::table('cpu_users')->where('user_id', Auth::id())->exists();
    }

    private function isPrApprover(): bool
    {
        return PrApprover::where('user_id', Auth::id())->exists();
    }

    // ── STO Actions ─────────────────────────────────────────────────────────

    public function sendSto(int $id): void
    {
        $sto = StockTransferOrder::findOrFail($id);
        if ($sto->status !== 'draft') return;
        $sto->update(['status' => 'sent']);
        session()->flash('success', "STO {$sto->sto_number} sent to outlet.");
    }

    public function receiveSto(int $id): void
    {
        $sto = StockTransferOrder::findOrFail($id);
        if ($sto->status !== 'sent') return;
        $sto->update(['status' => 'received', 'received_by' => Auth::id()]);
        session()->flash('success', "STO {$sto->sto_number} received.");
    }

    public function cancelSto(int $id): void
    {
        $sto = StockTransferOrder::findOrFail($id);
        if (in_array($sto->status, ['draft', 'sent'])) {
            $sto->update(['status' => 'cancelled']);
            session()->flash('success', "STO {$sto->sto_number} cancelled.");
        }
    }

    // ── PR Actions ──────────────────────────────────────────────────────────

    public function submitPr(int $id): void
    {
        $pr = PurchaseRequest::findOrFail($id);
        if ($pr->status !== 'draft') return;

        $requiresApproval = Auth::user()->company?->require_pr_approval ?? false;

        if ($requiresApproval) {
            $pr->update(['status' => 'submitted']);
            session()->flash('success', 'Purchase request submitted for approval.');
        } else {
            $pr->update([
                'status'      => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
            session()->flash('success', 'Purchase request approved.');
        }
    }

    public function approvePr(int $id): void
    {
        $pr = PurchaseRequest::findOrFail($id);
        if ($pr->status !== 'submitted') return;

        if (! PrApprover::isApproverFor(Auth::id(), $pr->outlet_id, $pr->department_id)) return;

        $pr->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        session()->flash('success', 'Purchase request approved.');
    }

    public function rejectPr(int $id, ?string $reason = null): void
    {
        $pr = PurchaseRequest::findOrFail($id);
        if ($pr->status !== 'submitted') return;

        if (! PrApprover::isApproverFor(Auth::id(), $pr->outlet_id, $pr->department_id)) return;

        $pr->update([
            'status'          => 'rejected',
            'approved_by'     => Auth::id(),
            'rejected_reason' => $reason ?: $this->rejectReason ?: 'Rejected by approver',
        ]);
        $this->rejectReason = '';
        session()->flash('success', 'Purchase request rejected.');
    }

    public function cancelPr(int $id): void
    {
        $pr = PurchaseRequest::findOrFail($id);
        if (in_array($pr->status, ['draft', 'submitted'])) {
            $pr->update(['status' => 'cancelled']);
            session()->flash('success', 'Purchase request cancelled.');
        }
    }

    public function deletePr(int $id): void
    {
        $pr = PurchaseRequest::findOrFail($id);
        if ($pr->status === 'draft') {
            $pr->delete();
            session()->flash('success', 'Purchase request deleted.');
        }
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

            if ($this->shouldDirectSupplierOrder()) {
                $this->sendPoEmail($po);
            } elseif ($this->shouldAutoGenerateDo()) {
                $this->autoGenerateDo($po);
                session()->flash('success', 'PO approved — DO and GRN auto-generated.');
            } else {
                session()->flash('success', 'PO approved and sent to purchasing team.');
            }
        }
    }

    public function approvePo(int $id): void
    {
        $po = PurchaseOrder::findOrFail($id);

        if ($po->status !== 'submitted') return;

        // Check user is an appointed approver for this PO's outlet + department
        if (! PoApprover::isApproverFor(Auth::id(), $po->outlet_id, $po->department_id)) return;

        $po->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
        ]);

        if ($this->shouldDirectSupplierOrder()) {
            $this->sendPoEmail($po);
        } elseif ($this->shouldAutoGenerateDo()) {
            $this->autoGenerateDo($po);
            session()->flash('success', 'PO approved — DO and GRN auto-generated.');
        } else {
            session()->flash('success', 'PO approved and sent to purchasing team.');
        }
    }

    public function rejectPo(int $id): void
    {
        $po = PurchaseOrder::findOrFail($id);

        if ($po->status !== 'submitted') return;

        if (! PoApprover::isApproverFor(Auth::id(), $po->outlet_id, $po->department_id)) return;

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

    /**
     * System Admin: soft-delete a PO and cascade to related DO, GRN, PurchaseRecord.
     */
    public function adminDeletePo(int $id): void
    {
        if (! Auth::user()->isSystemRole()) {
            session()->flash('error', 'Unauthorized.');
            return;
        }

        DB::transaction(function () use ($id) {
            $po = PurchaseOrder::with(['deliveryOrders.goodsReceivedNotes'])->findOrFail($id);

            // Cascade: soft-delete related GRNs → DOs → PurchaseRecords
            foreach ($po->deliveryOrders as $do) {
                foreach ($do->goodsReceivedNotes as $grn) {
                    $grn->lines()->delete();
                    $grn->delete();
                }
                // Soft-delete related purchase records
                PurchaseRecord::where('delivery_order_id', $do->id)->each(function ($pr) {
                    $pr->lines()->delete();
                    $pr->delete();
                });
                $do->lines()->delete();
                $do->delete();
            }

            // Also catch GRNs linked directly to PO (without DO)
            GoodsReceivedNote::where('purchase_order_id', $po->id)->each(function ($grn) {
                $grn->lines()->delete();
                $grn->delete();
            });

            $po->lines()->delete();
            $po->delete();
        });

        session()->flash('success', 'Purchase order and related documents deleted.');
    }

    /**
     * System Admin: soft-delete a DO and cascade to related GRN, PurchaseRecord.
     */
    public function adminDeleteDo(int $id): void
    {
        if (! Auth::user()->isSystemRole()) {
            session()->flash('error', 'Unauthorized.');
            return;
        }

        DB::transaction(function () use ($id) {
            $do = DeliveryOrder::with('goodsReceivedNotes')->findOrFail($id);

            foreach ($do->goodsReceivedNotes as $grn) {
                $grn->lines()->delete();
                $grn->delete();
            }

            PurchaseRecord::where('delivery_order_id', $do->id)->each(function ($pr) {
                $pr->lines()->delete();
                $pr->delete();
            });

            $do->lines()->delete();
            $do->delete();
        });

        session()->flash('success', 'Delivery order and related documents deleted.');
    }

    /**
     * System Admin: soft-delete a GRN and its related PurchaseRecord.
     */
    public function adminDeleteGrn(int $id): void
    {
        if (! Auth::user()->isSystemRole()) {
            session()->flash('error', 'Unauthorized.');
            return;
        }

        DB::transaction(function () use ($id) {
            $grn = GoodsReceivedNote::findOrFail($id);

            // Soft-delete related purchase record (linked via DO)
            if ($grn->delivery_order_id) {
                PurchaseRecord::where('delivery_order_id', $grn->delivery_order_id)->each(function ($pr) {
                    $pr->lines()->delete();
                    $pr->delete();
                });
            }

            $grn->lines()->delete();
            $grn->delete();
        });

        session()->flash('success', 'Goods received note deleted.');
    }

    /**
     * Business Manager / Admin: roll back an approved PO to draft for amendment.
     * Only allowed if no DOs have been created from this PO yet.
     */
    public function rollbackPo(int $id): void
    {
        $user = Auth::user();
        if (! $user->isSystemRole() && ! $user->hasCapability('can_delete_records')) {
            session()->flash('error', 'Unauthorized.');
            return;
        }

        $po = PurchaseOrder::findOrFail($id);

        if ($po->status !== 'approved') {
            session()->flash('error', 'Only approved POs can be rolled back.');
            return;
        }

        // Safety: block if any DOs already created from this PO
        if ($po->deliveryOrders()->count() > 0) {
            session()->flash('error', 'Cannot roll back — this PO already has Delivery Orders created.');
            return;
        }

        $po->update([
            'status'      => 'draft',
            'approved_by' => null,
        ]);

        session()->flash('success', "PO {$po->po_number} rolled back to draft for amendment.");
    }

    // ── Direct Supplier Order ─────────────────────────────────────────────────

    private function shouldDirectSupplierOrder(): bool
    {
        return (bool) (Auth::user()->company?->direct_supplier_order ?? false);
    }

    private function sendPoEmail(PurchaseOrder $po): void
    {
        $result = PoEmailService::sendApprovedPoEmail($po);

        if ($result['success']) {
            $po->update(['status' => 'sent']);
            session()->flash('success', 'PO approved and emailed to supplier.');
        } else {
            session()->flash('error', 'PO approved but email failed: ' . $result['message']);
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

    // ── Auto-generate DO ──────────────────────────────────────────────────────

    private function shouldAutoGenerateDo(): bool
    {
        return (bool) (Auth::user()->company?->auto_generate_do ?? false);
    }

    private function autoGenerateDo(PurchaseOrder $po): void
    {
        $po->loadMissing('lines');

        DB::transaction(function () use ($po) {
            $doNumber  = $this->generateDoNumber();
            $grnNumber = $this->generateGrnNumber();
            $total = $po->lines->sum(fn ($l) => floatval($l->quantity) * floatval($l->unit_cost));

            $do = DeliveryOrder::create([
                'company_id'        => $po->company_id,
                'outlet_id'         => $po->outlet_id,
                'purchase_order_id' => $po->id,
                'supplier_id'       => $po->supplier_id,
                'do_number'         => $doNumber,
                'status'            => 'pending',
                'delivery_date'     => $po->expected_delivery_date ?? now()->addDay(),
                'notes'             => $po->notes,
                'created_by'        => Auth::id(),
            ]);

            foreach ($po->lines as $line) {
                $do->lines()->create([
                    'ingredient_id'     => $line->ingredient_id,
                    'ordered_quantity'   => $line->quantity,
                    'delivered_quantity' => 0,
                    'uom_id'            => $line->uom_id,
                    'unit_cost'         => $line->unit_cost,
                    'condition'         => 'good',
                ]);
            }

            $grn = GoodsReceivedNote::create([
                'company_id'        => $po->company_id,
                'outlet_id'         => $po->outlet_id,
                'delivery_order_id' => $do->id,
                'purchase_order_id' => $po->id,
                'supplier_id'       => $po->supplier_id,
                'grn_number'        => $grnNumber,
                'status'            => 'pending',
                'total_amount'      => round($total, 4),
                'created_by'        => Auth::id(),
            ]);

            foreach ($po->lines as $line) {
                $grn->lines()->create([
                    'ingredient_id'     => $line->ingredient_id,
                    'expected_quantity'  => $line->quantity,
                    'received_quantity'  => 0,
                    'uom_id'            => $line->uom_id,
                    'unit_cost'         => $line->unit_cost,
                    'total_cost'        => round(floatval($line->quantity) * floatval($line->unit_cost), 4),
                    'condition'         => 'good',
                ]);
            }

            $po->update(['status' => 'sent']);
        });
    }

    private function generateDoNumber(): string
    {
        $prefix = 'DO-' . now()->format('Ymd') . '-';
        $last = DeliveryOrder::where('do_number', 'like', $prefix . '%')
            ->orderByDesc('do_number')->value('do_number');
        $seq = $last ? ((int) substr($last, -3) + 1) : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    private function generateGrnNumber(): string
    {
        $prefix = 'GRN-' . now()->format('Ymd') . '-';
        $last = GoodsReceivedNote::where('grn_number', 'like', $prefix . '%')
            ->orderByDesc('grn_number')->value('grn_number');
        $seq = $last ? ((int) substr($last, -3) + 1) : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    // ── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        $user = Auth::user();
        $isPurchasing = $this->isPurchasingRole();
        $isAppointed = $this->isAppointed();
        $seesAll = $this->seesAllOutlets();
        $canCreatePo = true; // All roles can create POs
        $approverOutletIds = $this->approverOutletIds();
        $cpuMode = $this->isCpuMode();
        $isCpuUser = $cpuMode ? ($this->isCpuUser() || $isPurchasing) : false;
        $isPrApprover = $this->isPrApprover();

        $data = match ($this->tab) {
            'pr'  => $this->getPrData($seesAll, $isCpuUser || $seesAll),
            'sto' => $this->getStoData($seesAll),
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

        $isSystemAdmin = $user->isSystemRole();
        $canRollbackPo = $user->isSystemRole() || $user->hasCapability('can_delete_records');

        $approverAssignments = $isAppointed ? $this->approverAssignments() : [];

        return view('livewire.purchasing.index', array_merge($data, [
            'suppliers'              => $suppliers,
            'outlets'                => $outlets,
            'isPurchasing'           => $isPurchasing,
            'isAppointed'            => $isAppointed,
            'approverOutletIds'      => $approverOutletIds,
            'approverAssignments'    => $approverAssignments,
            'seesAllOutlets'     => $seesAll,
            'canCreatePo'        => $canCreatePo,
            'stats'              => $stats,
            'requirePoApproval'  => $requirePoApproval,
            'showPrice'          => $showPrice,
            'isSystemAdmin'      => $isSystemAdmin,
            'canRollbackPo'      => $canRollbackPo,
            'cpuMode'            => $cpuMode,
            'isCpuUser'          => $isCpuUser,
            'isPrApprover'       => $isPrApprover,
        ]))->layout('layouts.app', ['title' => 'Purchasing']);
    }

    // ── Data Builders ────────────────────────────────────────────────────────

    private function getPoData(bool $seesAll): array
    {
        $query = PurchaseOrder::with(['supplier', 'outlet', 'lines'])->withCount('lines');
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

    private function getPrData(bool $seesAll, bool $isCpuUser): array
    {
        $query = PurchaseRequest::with(['outlet', 'createdBy'])->withCount('lines');

        if ($isCpuUser || $seesAll) {
            if ($this->outletFilter) {
                $query->where('outlet_id', $this->outletFilter);
            }
        } else {
            $this->scopeByOutlet($query);
        }

        if ($this->search) {
            $query->where('pr_number', 'like', '%' . $this->search . '%');
        }
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->dateFrom) {
            $query->where('requested_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('requested_date', '<=', $this->dateTo);
        }

        $purchaseRequests = $query->orderByDesc('requested_date')->orderByDesc('id')->paginate(15);

        return ['purchaseRequests' => $purchaseRequests];
    }

    private function getStoData(bool $seesAll): array
    {
        $query = StockTransferOrder::with(['cpu', 'toOutlet'])->withCount('lines');

        if (! $seesAll) {
            $this->scopeByOutlet($query, 'to_outlet_id');
        } elseif ($this->outletFilter) {
            $query->where('to_outlet_id', $this->outletFilter);
        }

        if ($this->search) {
            $query->where('sto_number', 'like', '%' . $this->search . '%');
        }
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->dateFrom) {
            $query->where('transfer_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('transfer_date', '<=', $this->dateTo);
        }

        $stockTransfers = $query->orderByDesc('transfer_date')->orderByDesc('id')->paginate(15);

        return ['stockTransfers' => $stockTransfers];
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
            $awaitingQ = PurchaseOrder::where('status', 'submitted');
            PoApprover::scopeApprovablePos($awaitingQ, Auth::id());
            $awaitingApproval = $awaitingQ->count();
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
