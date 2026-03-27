<?php

namespace App\Livewire\Reports\Order;

use App\Models\PurchaseOrderLine;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class OrderItemsByBranch extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function exportCsv()
    {
        $rows = $this->buildQuery()->get();

        return $this->exportCsvDownload('order-items-by-branch.csv', [
            'Outlet', 'Ingredient', 'Total Quantity', 'Total Cost',
        ], $rows->map(fn ($row) => [
            $row->outlet_name,
            $row->ingredient_name,
            number_format((float) $row->total_quantity, 2),
            number_format((float) $row->total_cost, 2),
        ])->toArray());
    }

    public function render()
    {
        $items = $this->buildQuery()->paginate(25);

        return view('livewire.reports.order.order-items-by-branch', [
            'items'     => $items,
            'outlets'   => $this->getOutlets(),
            'suppliers' => $this->getSuppliers(),
        ])->layout('layouts.app', ['title' => 'Order Items By Branch']);
    }

    private function buildQuery()
    {
        $query = PurchaseOrderLine::join('purchase_orders as po', 'po.id', '=', 'purchase_order_lines.purchase_order_id')
            ->join('outlets as o', 'o.id', '=', 'po.outlet_id')
            ->join('ingredients as i', 'i.id', '=', 'purchase_order_lines.ingredient_id')
            ->select(
                'o.id as outlet_id',
                'o.name as outlet_name',
                'i.name as ingredient_name',
                DB::raw('SUM(purchase_order_lines.quantity) as total_quantity'),
                DB::raw('SUM(purchase_order_lines.total_cost) as total_cost'),
            )
            ->whereBetween('po.order_date', [$this->dateFrom, $this->dateTo])
            ->whereNull('po.deleted_at');

        if ($this->outletFilter) {
            $query->where('po.outlet_id', $this->outletFilter);
        }

        if ($this->supplierFilter) {
            $query->where('po.supplier_id', $this->supplierFilter);
        }

        return $query->groupBy('o.id', 'o.name', 'i.name')
            ->orderBy('o.name')
            ->orderBy('i.name');
    }
}
