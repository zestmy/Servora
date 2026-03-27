<?php

namespace App\Livewire\Reports\InventoryAction;

use App\Models\StockTake;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Livewire\Component;
use Livewire\WithPagination;

class StockCount extends Component
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

        return $this->exportCsvDownload('stock-count.csv', [
            'Reference', 'Outlet', 'Date', 'Status', 'Total Lines', 'Completed By',
        ], $rows->map(fn ($r) => [
            $r->reference_number, $r->outlet?->name, $r->stock_take_date->format('d M Y'),
            ucfirst($r->status), $r->lines_count, $r->createdBy?->name,
        ])->toArray());
    }

    public function render()
    {
        $records = $this->buildQuery()->paginate(25);
        $outlets = $this->getOutlets();

        return view('livewire.reports.inventory-action.stock-count', compact('records', 'outlets'))
            ->layout('layouts.app', ['title' => 'Stock Count']);
    }

    private function buildQuery()
    {
        return StockTake::query()
            ->with(['outlet', 'createdBy'])
            ->withCount('lines')
            ->when($this->outletFilter, fn ($q) => $q->where('outlet_id', $this->outletFilter))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->whereBetween('stock_take_date', [$this->dateFrom, $this->dateTo])
            ->orderByDesc('stock_take_date');
    }
}
