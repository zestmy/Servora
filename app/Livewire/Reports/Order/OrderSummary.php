<?php

namespace App\Livewire\Reports\Order;

use App\Models\PurchaseOrder;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class OrderSummary extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function exportCsv()
    {
        $rows = $this->buildQuery();

        return $this->exportCsvDownload('order-summary.csv', [
            'Month', 'Order Count', 'Total Value', 'Average Value',
        ], $rows->map(fn ($row) => [
            $row->month_label,
            $row->order_count,
            number_format((float) $row->total_value, 2),
            number_format((float) $row->avg_value, 2),
        ])->toArray());
    }

    public function render()
    {
        $summary = $this->buildQuery();

        return view('livewire.reports.order.order-summary', [
            'summary'   => $summary,
            'outlets'   => $this->getOutlets(),
            'suppliers' => $this->getSuppliers(),
        ])->layout('layouts.app', ['title' => 'Order Summary']);
    }

    private function buildQuery()
    {
        $query = PurchaseOrder::select(
                DB::raw("DATE_FORMAT(order_date, '%b %Y') as month_label"),
                DB::raw("DATE_FORMAT(order_date, '%Y-%m') as month_sort"),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total_amount) as total_value'),
                DB::raw('AVG(total_amount) as avg_value'),
            )
            ->whereBetween('order_date', [$this->dateFrom, $this->dateTo]);

        $this->scopeByOutlet($query);

        if ($this->outletFilter) {
            $query->where('outlet_id', $this->outletFilter);
        }

        return $query->groupBy('month_label', 'month_sort')
            ->orderByDesc('month_sort')
            ->get();
    }
}
