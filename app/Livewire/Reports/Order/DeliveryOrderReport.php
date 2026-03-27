<?php

namespace App\Livewire\Reports\Order;

use App\Models\DeliveryOrder;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Livewire\Component;
use Livewire\WithPagination;

class DeliveryOrderReport extends Component
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

        return $this->exportCsvDownload('delivery-orders.csv', [
            'DO Number', 'PO Number', 'Outlet', 'Supplier', 'Delivery Date', 'Status', 'Sequence', 'Items',
        ], $rows->map(fn ($do) => [
            $do->do_number,
            $do->purchaseOrder?->po_number ?? '-',
            $do->outlet?->name ?? '-',
            $do->supplier?->name ?? '-',
            $do->delivery_date?->format('d M Y'),
            ucfirst($do->status),
            $do->delivery_sequence,
            $do->lines_count,
        ])->toArray());
    }

    public function render()
    {
        $deliveries = $this->buildQuery()->paginate(25);

        return view('livewire.reports.order.delivery-order-report', [
            'deliveries' => $deliveries,
            'outlets'    => $this->getOutlets(),
            'suppliers'  => $this->getSuppliers(),
        ])->layout('layouts.app', ['title' => 'Delivery Order Report']);
    }

    private function buildQuery()
    {
        $query = DeliveryOrder::with(['outlet', 'supplier', 'purchaseOrder'])
            ->withCount('lines')
            ->whereBetween('delivery_date', [$this->dateFrom, $this->dateTo]);

        $this->scopeByOutlet($query);

        if ($this->outletFilter) {
            $query->where('outlet_id', $this->outletFilter);
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderByDesc('delivery_date');
    }
}
