<?php

namespace App\Livewire\Reports\Kitchen;

use App\Models\CentralKitchen;
use App\Models\ProductionOrder;
use App\Services\CsvExportService;
use App\Traits\ReportFilters;
use Livewire\Component;
use Livewire\WithPagination;

class ProductionHistory extends Component
{
    use WithPagination, ReportFilters;

    public ?int $kitchenFilter = null;
    public string $statusFilter = '';

    public function updatedKitchenFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    public function mount(): void { $this->mountReportFilters(); }

    public function exportCsv()
    {
        $rows = $this->buildQuery()->get()->map(fn ($o) => [
            $o->order_number, $o->kitchen?->name, $o->production_date->format('Y-m-d'),
            ucfirst($o->status), $o->lines->count(),
            $o->lines->sum(fn ($l) => floatval($l->planned_quantity)),
            $o->lines->sum(fn ($l) => floatval($l->actual_quantity ?? 0)),
        ]);
        return CsvExportService::download('production-history.csv',
            ['Order #', 'Kitchen', 'Date', 'Status', 'Items', 'Planned Qty', 'Actual Qty'], $rows);
    }

    private function buildQuery()
    {
        $query = ProductionOrder::with(['kitchen', 'lines.recipe'])->withCount('lines');
        if ($this->kitchenFilter) $query->where('kitchen_id', $this->kitchenFilter);
        if ($this->statusFilter) $query->where('status', $this->statusFilter);
        if ($this->dateFrom) $query->where('production_date', '>=', $this->dateFrom);
        if ($this->dateTo) $query->where('production_date', '<=', $this->dateTo);
        return $query->orderByDesc('production_date');
    }

    public function render()
    {
        $orders = $this->buildQuery()->paginate(25);
        $kitchens = CentralKitchen::active()->orderBy('name')->get();
        return view('livewire.reports.kitchen.production-history', compact('orders', 'kitchens'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Production History']);
    }
}
