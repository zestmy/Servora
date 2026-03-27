<?php

namespace App\Livewire\Reports\InventoryAction;

use App\Models\OrderAdjustmentLog;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Livewire\Component;
use Livewire\WithPagination;

class StockAdjustment extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function exportCsv()
    {
        $rows = $this->buildQuery()->get();

        return $this->exportCsvDownload('stock-adjustment.csv', [
            'Date', 'Document Type', 'Document Ref', 'Field Changed', 'Old Value', 'New Value', 'Reason', 'Adjusted By',
        ], $rows->map(fn ($r) => [
            $r->created_at->format('d M Y H:i'),
            class_basename($r->adjustable_type),
            $r->adjustable?->reference_number ?? $r->adjustable_id,
            $r->field,
            $r->old_value,
            $r->new_value,
            $r->reason,
            $r->adjustedBy?->name,
        ])->toArray());
    }

    public function render()
    {
        $logs = $this->buildQuery()->paginate(25);
        $outlets = $this->getOutlets();

        return view('livewire.reports.inventory-action.stock-adjustment', compact('logs', 'outlets'))
            ->layout('layouts.app', ['title' => 'Stock Adjustment']);
    }

    private function buildQuery()
    {
        return OrderAdjustmentLog::query()
            ->with(['adjustedBy'])
            ->whereBetween('created_at', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
            ->orderByDesc('created_at');
    }
}
