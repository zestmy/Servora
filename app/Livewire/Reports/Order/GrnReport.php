<?php

namespace App\Livewire\Reports\Order;

use App\Models\GoodsReceivedNote;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Livewire\Component;
use Livewire\WithPagination;

class GrnReport extends Component
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

        return $this->exportCsvDownload('grn-report.csv', [
            'GRN Number', 'PO Number', 'DO Number', 'Outlet', 'Supplier', 'Received Date', 'Status', 'Total Amount', 'Has Variance',
        ], $rows->map(fn ($grn) => [
            $grn->grn_number,
            $grn->purchaseOrder?->po_number ?? '-',
            $grn->deliveryOrder?->do_number ?? '-',
            $grn->outlet?->name ?? '-',
            $grn->supplier?->name ?? '-',
            $grn->received_date?->format('d M Y'),
            ucfirst($grn->status),
            number_format((float) $grn->total_amount, 2),
            $grn->lines->contains(fn ($l) => floatval($l->received_quantity) != floatval($l->expected_quantity)) ? 'Yes' : 'No',
        ])->toArray());
    }

    public function render()
    {
        $grns = $this->buildQuery()->paginate(25);

        return view('livewire.reports.order.grn-report', [
            'grns'      => $grns,
            'outlets'   => $this->getOutlets(),
            'suppliers' => $this->getSuppliers(),
        ])->layout('layouts.app', ['title' => 'GRN Report']);
    }

    private function buildQuery()
    {
        $query = GoodsReceivedNote::with(['outlet', 'supplier', 'purchaseOrder', 'deliveryOrder', 'lines'])
            ->whereBetween('received_date', [$this->dateFrom, $this->dateTo]);

        $this->scopeByOutlet($query);

        if ($this->outletFilter) {
            $query->where('outlet_id', $this->outletFilter);
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderByDesc('received_date');
    }
}
