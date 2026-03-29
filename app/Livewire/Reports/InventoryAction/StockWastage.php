<?php

namespace App\Livewire\Reports\InventoryAction;

use App\Models\WastageRecordLine;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Livewire\Component;
use Livewire\WithPagination;

class StockWastage extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function exportCsv()
    {
        $rows = $this->buildQuery()->get();

        return $this->exportCsvDownload('stock-wastage.csv', [
            'Date', 'Outlet', 'Reference', 'Ingredient', 'Quantity', 'UOM', 'Reason', 'Cost',
        ], $rows->map(fn ($r) => [
            $r->wastageRecord->wastage_date->format('d M Y'),
            $r->wastageRecord->outlet?->name,
            $r->wastageRecord->reference_number,
            $r->ingredient?->name,
            $r->quantity,
            $r->uom?->abbreviation,
            $r->reason,
            $r->total_cost,
        ])->toArray());
    }

    public function render()
    {
        $lines = $this->buildQuery()->paginate(25);
        $outlets = $this->getOutlets();

        return view('livewire.reports.inventory-action.stock-wastage', compact('lines', 'outlets'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Stock Wastage']);
    }

    private function buildQuery()
    {
        return WastageRecordLine::query()
            ->with(['wastageRecord.outlet', 'ingredient', 'uom'])
            ->whereHas('wastageRecord', function ($q) {
                $q->whereBetween('wastage_date', [$this->dateFrom, $this->dateTo]);
                if ($this->outletFilter) {
                    $q->where('outlet_id', $this->outletFilter);
                }
            })
            ->orderByDesc(
                \App\Models\WastageRecord::select('wastage_date')
                    ->whereColumn('wastage_records.id', 'wastage_record_lines.wastage_record_id')
                    ->limit(1)
            );
    }
}
