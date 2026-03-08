<?php

namespace App\Livewire\Reports;

use App\Services\CostSummaryService;
use App\Services\CsvExportService;
use App\Traits\ScopesToActiveOutlet;
use Carbon\Carbon;
use Livewire\Component;

class Index extends Component
{
    use ScopesToActiveOutlet;

    public string $period = '';
    public array $summary = [];

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
        $this->loadSummary();
    }

    public function updatedPeriod(): void
    {
        $this->loadSummary();
    }

    public function loadSummary(): void
    {
        $service = new CostSummaryService();
        $this->summary = $service->generate(
            $this->period,
            $this->activeOutletId()
        );
    }

    public function previousMonth(): void
    {
        $this->period = Carbon::createFromFormat('Y-m', $this->period)->subMonth()->format('Y-m');
        $this->loadSummary();
    }

    public function nextMonth(): void
    {
        $this->period = Carbon::createFromFormat('Y-m', $this->period)->addMonth()->format('Y-m');
        $this->loadSummary();
    }

    public function exportCsv()
    {
        if (empty($this->summary['categories'])) {
            return;
        }

        $cats = $this->summary['categories'];
        $totals = $this->summary['totals'];
        $periodLabel = Carbon::createFromFormat('Y-m', $this->period)->format('F Y');

        // Build headers: Metric, Cat1, Cat2, ..., Total
        $headers = ['Metric'];
        foreach ($cats as $cat) {
            $headers[] = $cat['name'];
        }
        $headers[] = 'Total';

        $rows = [];

        // Revenue
        $row = ['Revenue'];
        foreach ($cats as $cat) { $row[] = $cat['revenue']; }
        $row[] = $totals['revenue'];
        $rows[] = $row;

        // Opening Stock
        $row = ['Opening Stock'];
        foreach ($cats as $cat) { $row[] = $cat['opening_stock']; }
        $row[] = $totals['opening_stock'];
        $rows[] = $row;

        // Purchases
        $row = ['(+) Purchases'];
        foreach ($cats as $cat) { $row[] = $cat['purchases']; }
        $row[] = $totals['purchases'];
        $rows[] = $row;

        // Transfer In
        $row = ['(+) Transfer In'];
        foreach ($cats as $cat) { $row[] = $cat['transfer_in']; }
        $row[] = $totals['transfer_in'];
        $rows[] = $row;

        // Transfer Out
        $row = ['(-) Transfer Out'];
        foreach ($cats as $cat) { $row[] = $cat['transfer_out']; }
        $row[] = $totals['transfer_out'];
        $rows[] = $row;

        // Closing Stock
        $row = ['(-) Closing Stock'];
        foreach ($cats as $cat) { $row[] = $cat['closing_stock']; }
        $row[] = $totals['closing_stock'];
        $rows[] = $row;

        // COGS
        $row = ['COGS'];
        foreach ($cats as $cat) { $row[] = $cat['cogs']; }
        $row[] = $totals['cogs'];
        $rows[] = $row;

        // Cost %
        $row = ['Cost %'];
        foreach ($cats as $cat) { $row[] = $cat['cost_pct'] . '%'; }
        $row[] = $totals['cost_pct'] . '%';
        $rows[] = $row;

        // Wastage & Staff Meals totals (single total column, not per-category)
        $row = ['Wastage Total'];
        foreach ($cats as $cat) { $row[] = ''; }
        $row[] = $totals['wastage'];
        $rows[] = $row;

        $row = ['Staff Meals Total'];
        foreach ($cats as $cat) { $row[] = ''; }
        $row[] = $totals['staff_meals'];
        $rows[] = $row;

        $filename = "cost-summary-{$this->period}.csv";
        return CsvExportService::download($filename, $headers, $rows);
    }

    public function render()
    {
        return view('livewire.reports.index')
            ->layout('layouts.app', ['title' => 'Reports']);
    }
}
