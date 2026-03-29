<?php

namespace App\Livewire\Reports\InventoryAction;

use App\Models\OutletTransfer;
use App\Models\StockTransferOrder;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class StockTransferHistory extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function exportCsv()
    {
        $rows = $this->buildCombined();

        return $this->exportCsvDownload('stock-transfer-history.csv', [
            'Reference', 'From', 'To', 'Date', 'Type', 'Items', 'Status',
        ], $rows->map(fn ($r) => [
            $r['reference'], $r['from'], $r['to'], $r['date'], $r['type'], $r['items'], $r['status'],
        ])->toArray());
    }

    public function render()
    {
        $transfers = $this->buildCombined();
        $total = $transfers->count();

        // Manual pagination
        $page = $this->getPage();
        $perPage = 25;
        $paginatedItems = $transfers->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedItems, $total, $perPage, $page,
            ['path' => request()->url()]
        );

        $outlets = $this->getOutlets();

        return view('livewire.reports.inventory-action.stock-transfer-history', [
            'transfers' => $paginator,
            'outlets' => $outlets,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Stock Transfer History']);
    }

    private function buildCombined(): Collection
    {
        $from = $this->dateFrom;
        $to = $this->dateTo;
        $outletId = $this->outletFilter;

        // Outlet Transfers
        $outletTransfers = OutletTransfer::query()
            ->with(['fromOutlet', 'toOutlet'])
            ->withCount('lines')
            ->whereBetween('transfer_date', [$from, $to])
            ->when($outletId, fn ($q) => $q->where(function ($q2) use ($outletId) {
                $q2->where('from_outlet_id', $outletId)->orWhere('to_outlet_id', $outletId);
            }))
            ->get()
            ->map(fn ($t) => [
                'reference' => $t->transfer_number,
                'from' => $t->fromOutlet?->name ?? '-',
                'to' => $t->toOutlet?->name ?? '-',
                'date' => $t->transfer_date->format('d M Y'),
                'sort_date' => $t->transfer_date,
                'type' => 'Outlet Transfer',
                'items' => $t->lines_count,
                'status' => ucfirst($t->status ?? 'completed'),
            ]);

        // Stock Transfer Orders (CPU STO)
        $stoTransfers = StockTransferOrder::query()
            ->with(['cpu', 'toOutlet'])
            ->withCount('lines')
            ->whereBetween('transfer_date', [$from, $to])
            ->when($outletId, fn ($q) => $q->where('to_outlet_id', $outletId))
            ->get()
            ->map(fn ($t) => [
                'reference' => $t->sto_number,
                'from' => $t->cpu?->name ?? 'CPU',
                'to' => $t->toOutlet?->name ?? '-',
                'date' => $t->transfer_date->format('d M Y'),
                'sort_date' => $t->transfer_date,
                'type' => 'CPU STO',
                'items' => $t->lines_count,
                'status' => ucfirst($t->status ?? 'completed'),
            ]);

        return $outletTransfers->concat($stoTransfers)
            ->sortByDesc('sort_date')
            ->values();
    }
}
