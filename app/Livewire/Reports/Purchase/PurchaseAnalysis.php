<?php

namespace App\Livewire\Reports\Purchase;

use App\Models\PurchaseRecord;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseAnalysis extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function exportCsv()
    {
        $rows = $this->buildQuery()->get();

        return $this->exportCsvDownload(
            'purchase-analysis-' . now()->format('Y-m-d') . '.csv',
            ['Supplier', 'Category', 'Total Spend', 'Item Count', 'Avg Cost/Item'],
            $rows->map(fn ($r) => [
                $r->supplier_name,
                $r->category_name ?? 'Uncategorised',
                number_format($r->total_spend, 2),
                $r->item_count,
                number_format($r->avg_cost_per_item, 2),
            ])->toArray()
        );
    }

    public function render()
    {
        $paginated = $this->buildQuery()->paginate(25);

        // Grand totals via subquery wrapping the grouped result
        $totals = DB::query()
            ->fromSub($this->buildQuery(), 'sub')
            ->selectRaw('SUM(sub.total_spend) as grand_total_spend')
            ->selectRaw('SUM(sub.item_count) as grand_item_count')
            ->first();

        $outlets   = $this->getOutlets();
        $suppliers = $this->getSuppliers();

        return view('livewire.reports.purchase.purchase-analysis', [
            'rows'      => $paginated,
            'totals'    => $totals,
            'outlets'   => $outlets,
            'suppliers' => $suppliers,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Purchase Analysis']);
    }

    private function buildQuery()
    {
        $query = PurchaseRecord::query()
            ->join('purchase_record_lines as prl', 'prl.purchase_record_id', '=', 'purchase_records.id')
            ->join('suppliers as s', 's.id', '=', 'purchase_records.supplier_id')
            ->leftJoin('ingredients as i', 'i.id', '=', 'prl.ingredient_id')
            ->leftJoin('ingredient_categories as ic', 'ic.id', '=', 'i.ingredient_category_id')
            ->whereBetween('purchase_records.purchase_date', [$this->dateFrom, $this->dateTo])
            ->select([
                's.name as supplier_name',
                DB::raw('COALESCE(ic.name, \'Uncategorised\') as category_name'),
                DB::raw('SUM(prl.total_cost) as total_spend'),
                DB::raw('COUNT(prl.id) as item_count'),
                DB::raw('AVG(prl.unit_cost) as avg_cost_per_item'),
            ])
            ->groupBy('s.name', 'category_name');

        if ($this->outletFilter) {
            $query->where('purchase_records.outlet_id', $this->outletFilter);
        }

        if ($this->supplierFilter) {
            $query->where('purchase_records.supplier_id', $this->supplierFilter);
        }

        $query->orderBy('s.name')->orderBy('category_name');

        return $query;
    }
}
