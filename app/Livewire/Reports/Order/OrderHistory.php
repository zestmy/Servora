<?php

namespace App\Livewire\Reports\Order;

use App\Models\PurchaseOrder;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Livewire\Component;
use Livewire\WithPagination;

class OrderHistory extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function exportCsv()
    {
        $rows = $this->buildQuery()->get();

        return $this->exportCsvDownload('order-history.csv', [
            'PO Number', 'Outlet', 'Supplier', 'Order Date', 'Status', 'Items', 'Total Amount',
        ], $rows->map(fn ($po) => [
            $po->po_number,
            $po->outlet?->name ?? '-',
            $po->supplier?->name ?? '-',
            $po->order_date?->format('d M Y'),
            ucfirst($po->status),
            $po->lines_count,
            number_format((float) $po->total_amount, 2),
        ])->toArray());
    }

    public function render()
    {
        $orders = $this->buildQuery()->paginate(25);

        return view('livewire.reports.order.order-history', [
            'orders'    => $orders,
            'outlets'   => $this->getOutlets(),
            'suppliers' => $this->getSuppliers(),
        ])->layout('layouts.app', ['title' => 'Order History']);
    }

    private function buildQuery()
    {
        $query = PurchaseOrder::with(['outlet', 'supplier'])
            ->withCount('lines')
            ->whereBetween('order_date', [$this->dateFrom, $this->dateTo]);

        $this->scopeByOutlet($query);

        if ($this->outletFilter) {
            $query->where('outlet_id', $this->outletFilter);
        }

        if ($this->supplierFilter) {
            $query->where('supplier_id', $this->supplierFilter);
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderByDesc('order_date');
    }
}
