<?php

namespace App\Livewire\Reports\Kitchen;

use App\Models\ProductionLog;
use App\Traits\ReportFilters;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class YieldAnalysis extends Component
{
    use WithPagination, ReportFilters;

    public function mount(): void { $this->mountReportFilters(); }

    public function render()
    {
        $query = ProductionLog::query()
            ->select('recipe_id',
                DB::raw('COUNT(*) as batch_count'),
                DB::raw('AVG(yield_variance_pct) as avg_variance'),
                DB::raw('SUM(planned_yield) as total_planned'),
                DB::raw('SUM(actual_yield) as total_actual'),
                DB::raw('SUM(total_cost) as total_cost'))
            ->with('recipe')
            ->groupBy('recipe_id');

        if ($this->dateFrom) $query->where('produced_at', '>=', $this->dateFrom);
        if ($this->dateTo) $query->where('produced_at', '<=', $this->dateTo . ' 23:59:59');

        $recipes = $query->orderByRaw('AVG(yield_variance_pct) ASC')->paginate(25);

        return view('livewire.reports.kitchen.yield-analysis', compact('recipes'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Yield Analysis']);
    }
}
