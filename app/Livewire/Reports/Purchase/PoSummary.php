<?php

namespace App\Livewire\Reports\Purchase;

use App\Models\PurchaseOrder;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PoSummary extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function exportCsv()
    {
        $byStatus  = $this->buildStatusQuery()->get();
        $byPeriod  = $this->buildPeriodQuery()->get();

        $csvRows = [];

        // Status section
        $csvRows[] = ['--- By Status ---', '', '', ''];
        $csvRows[] = ['Status', 'Count', 'Total Value', 'Avg Value'];
        foreach ($byStatus as $row) {
            $csvRows[] = [
                ucfirst($row->status),
                $row->po_count,
                number_format($row->total_value, 2),
                number_format($row->avg_value, 2),
            ];
        }

        $csvRows[] = ['', '', '', ''];
        $csvRows[] = ['--- By Period ---', '', '', ''];
        $csvRows[] = ['Month', 'Count', 'Total Value', 'Avg Value'];
        foreach ($byPeriod as $row) {
            $csvRows[] = [
                $row->period,
                $row->po_count,
                number_format($row->total_value, 2),
                number_format($row->avg_value, 2),
            ];
        }

        return $this->exportCsvDownload(
            'po-summary-' . now()->format('Y-m-d') . '.csv',
            ['Label', 'Count', 'Total Value', 'Avg Value'],
            $csvRows
        );
    }

    public function render()
    {
        $byStatus = $this->buildStatusQuery()->paginate(25, ['*'], 'statusPage');
        $byPeriod = $this->buildPeriodQuery()->get();

        // Grand totals
        $totals = DB::query()
            ->fromSub($this->buildStatusQuery(), 'sub')
            ->selectRaw('SUM(sub.po_count) as grand_count')
            ->selectRaw('SUM(sub.total_value) as grand_total')
            ->first();

        $outlets   = $this->getOutlets();
        $suppliers = $this->getSuppliers();

        return view('livewire.reports.purchase.po-summary', [
            'byStatus'  => $byStatus,
            'byPeriod'  => $byPeriod,
            'totals'    => $totals,
            'outlets'   => $outlets,
            'suppliers' => $suppliers,
        ])->layout('layouts.app', ['title' => 'PO Summary']);
    }

    private function baseQuery()
    {
        $query = PurchaseOrder::query()
            ->whereBetween('order_date', [$this->dateFrom, $this->dateTo]);

        if ($this->outletFilter) {
            $query->where('outlet_id', $this->outletFilter);
        }

        if ($this->supplierFilter) {
            $query->where('supplier_id', $this->supplierFilter);
        }

        return $query;
    }

    private function buildStatusQuery()
    {
        return (clone $this->baseQuery())
            ->select([
                'status',
                DB::raw('COUNT(*) as po_count'),
                DB::raw('SUM(total_amount) as total_value'),
                DB::raw('AVG(total_amount) as avg_value'),
            ])
            ->groupBy('status')
            ->orderBy('status');
    }

    private function buildPeriodQuery()
    {
        return (clone $this->baseQuery())
            ->select([
                DB::raw("DATE_FORMAT(order_date, '%Y-%m') as period"),
                DB::raw('COUNT(*) as po_count'),
                DB::raw('SUM(total_amount) as total_value'),
                DB::raw('AVG(total_amount) as avg_value'),
            ])
            ->groupBy('period')
            ->orderBy('period');
    }
}
