<?php

namespace App\Livewire\Reports;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\SalesRecord;
use App\Services\CostSummaryService;
use App\Services\CsvExportService;
use App\Traits\ScopesToActiveOutlet;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    use ScopesToActiveOutlet;

    public string $activeTab = 'cost_summary'; // cost_summary | performance | cost_analysis | wastage
    public string $period = '';
    public string $mode = 'monthly'; // monthly | weekly
    public string $weekStart = '';
    public ?int $outletId = null;
    public array $summary = [];
    public array $dashboardData = [];

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
        $this->weekStart = now()->startOfWeek()->format('Y-m-d');
        $this->outletId = $this->activeOutletId();
        $this->loadData();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function updatedPeriod(): void
    {
        $this->loadData();
    }

    public function updatedMode(): void
    {
        $this->loadData();
    }

    public function updatedWeekStart(): void
    {
        if ($this->mode === 'weekly') {
            $this->loadData();
        }
    }

    public function updatedOutletId(): void
    {
        $this->loadData();
    }

    public function previousMonth(): void
    {
        $this->period = Carbon::createFromFormat('Y-m', $this->period)->subMonth()->format('Y-m');
        $this->loadData();
    }

    public function nextMonth(): void
    {
        $this->period = Carbon::createFromFormat('Y-m', $this->period)->addMonth()->format('Y-m');
        $this->loadData();
    }

    public function previousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->format('Y-m-d');
        $this->loadData();
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->format('Y-m-d');
        $this->loadData();
    }

    public function exportPdf()
    {
        $company = Company::find(Auth::user()->company_id);
        $outlet = $this->outletId ? Outlet::find($this->outletId) : null;
        $periodLabel = $this->periodLabel();

        switch ($this->activeTab) {
            case 'cost_summary':
                return $this->exportCostSummaryPdf($company, $outlet, $periodLabel);
            case 'performance':
                return $this->exportPerformancePdf($company, $outlet, $periodLabel);
            case 'cost_analysis':
                return $this->exportCostAnalysisPdf($company, $outlet, $periodLabel);
            case 'wastage':
                return $this->exportWastagePdf($company, $outlet, $periodLabel);
        }
    }

    public function exportCsv()
    {
        if (empty($this->summary['categories'])) {
            return;
        }

        $cats = $this->summary['categories'];
        $totals = $this->summary['totals'];

        $headers = ['Metric'];
        foreach ($cats as $cat) {
            $headers[] = $cat['name'];
        }
        $headers[] = 'Total';

        $rows = [];

        $row = ['Revenue'];
        foreach ($cats as $cat) { $row[] = $cat['revenue']; }
        $row[] = $totals['revenue'];
        $rows[] = $row;

        $row = ['Opening Stock'];
        foreach ($cats as $cat) { $row[] = $cat['opening_stock']; }
        $row[] = $totals['opening_stock'];
        $rows[] = $row;

        $row = ['(+) Purchases'];
        foreach ($cats as $cat) { $row[] = $cat['purchases']; }
        $row[] = $totals['purchases'];
        $rows[] = $row;

        $row = ['(+) Transfer In'];
        foreach ($cats as $cat) { $row[] = $cat['transfer_in']; }
        $row[] = $totals['transfer_in'];
        $rows[] = $row;

        $row = ['(-) Transfer Out'];
        foreach ($cats as $cat) { $row[] = $cat['transfer_out']; }
        $row[] = $totals['transfer_out'];
        $rows[] = $row;

        $row = ['(-) Closing Stock'];
        foreach ($cats as $cat) { $row[] = $cat['closing_stock']; }
        $row[] = $totals['closing_stock'];
        $rows[] = $row;

        $row = ['COGS'];
        foreach ($cats as $cat) { $row[] = $cat['cogs']; }
        $row[] = $totals['cogs'];
        $rows[] = $row;

        $row = ['Cost %'];
        foreach ($cats as $cat) { $row[] = $cat['cost_pct'] . '%'; }
        $row[] = $totals['cost_pct'] . '%';
        $rows[] = $row;

        $row = ['Wastage Total'];
        foreach ($cats as $cat) { $row[] = ''; }
        $row[] = $totals['wastage'];
        $rows[] = $row;

        $row = ['Staff Meals Total'];
        foreach ($cats as $cat) { $row[] = ''; }
        $row[] = $totals['staff_meals'];
        $rows[] = $row;

        $filename = $this->mode === 'weekly'
            ? "cost-summary-week-{$this->weekStart}.csv"
            : "cost-summary-{$this->period}.csv";

        return CsvExportService::download($filename, $headers, $rows);
    }

    public function render()
    {
        $outlets = Outlet::where('company_id', Auth::user()->company_id)->orderBy('name')->get();

        return view('livewire.reports.index', [
            'periodLabel' => $this->periodLabel(),
            'outlets' => $outlets,
        ])->layout('layouts.app', ['title' => 'Reports']);
    }

    private function loadData(): void
    {
        $this->loadSummary();
        $this->loadDashboardData();
    }

    private function loadSummary(): void
    {
        $service = new CostSummaryService();
        $outletId = $this->outletId ?: null;

        if ($this->mode === 'weekly' && $this->weekStart) {
            $start = Carbon::parse($this->weekStart)->startOfWeek();
            $end = $start->copy()->addDays(6);
            $this->weekStart = $start->format('Y-m-d');

            $this->summary = $service->generate(
                $start->format('Y-m'),
                $outletId,
                $start->format('Y-m-d'),
                $end->format('Y-m-d')
            );
        } else {
            $this->summary = $service->generate($this->period, $outletId);
        }
    }

    private function loadDashboardData(): void
    {
        $costService = new CostSummaryService();
        $outletId = $this->outletId ?: null;
        $costSummary = $costService->generate($this->period, $outletId);

        $prevPeriod = Carbon::createFromFormat('Y-m', $this->period)->subMonth()->format('Y-m');
        $prevSummary = $costService->generate($prevPeriod, $outletId);

        $date = Carbon::createFromFormat('Y-m', $this->period);
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        // Daily sales
        $dailySalesQuery = SalesRecord::whereBetween('sale_date', [$startOfMonth, $endOfMonth]);
        if ($outletId) {
            $dailySalesQuery->where('outlet_id', $outletId);
        }
        $dailySales = $dailySalesQuery
            ->selectRaw('sale_date, SUM(total_revenue) as revenue, SUM(pax) as pax')
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get()
            ->map(fn ($row) => [
                'label'    => $row->sale_date->format('M d'),
                'day'      => $row->sale_date->format('D'),
                'day_name' => $row->sale_date->format('l'),
                'revenue'  => round((float) $row->revenue, 2),
                'pax'      => (int) $row->pax,
                'avg'      => $row->pax > 0 ? round((float) $row->revenue / $row->pax, 2) : 0,
            ])
            ->toArray();

        // Day of week aggregation
        $dayAgg = [];
        foreach ($dailySales as $day) {
            $name = $day['day_name'];
            if (!isset($dayAgg[$name])) {
                $dayAgg[$name] = ['revenue' => 0, 'pax' => 0, 'count' => 0];
            }
            $dayAgg[$name]['revenue'] += $day['revenue'];
            $dayAgg[$name]['pax'] += $day['pax'];
            $dayAgg[$name]['count']++;
        }
        $dayOfWeek = [];
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $dayName) {
            if (isset($dayAgg[$dayName])) {
                $d = $dayAgg[$dayName];
                $dayOfWeek[] = [
                    'day'         => substr($dayName, 0, 3),
                    'avg_revenue' => round($d['revenue'] / $d['count'], 2),
                    'avg_pax'     => round($d['pax'] / $d['count']),
                ];
            }
        }

        // Top wastage items
        $topWastage = [];
        if (!empty($costSummary['wastage_detail']['groups'])) {
            foreach ($costSummary['wastage_detail']['groups'] as $catName => $group) {
                foreach ($group['items'] as $item) {
                    $topWastage[] = [
                        'name'       => $item['name'],
                        'category'   => $catName,
                        'total_cost' => $item['total_cost'],
                        'quantity'   => $item['quantity'],
                        'uom'        => $item['uom'],
                        'type'       => $item['type'],
                    ];
                }
            }
            usort($topWastage, fn ($a, $b) => $b['total_cost'] <=> $a['total_cost']);
            $topWastage = array_slice($topWastage, 0, 10);
        }

        // Wastage by category for chart
        $wastageByCat = [];
        if (!empty($costSummary['wastage_detail']['groups'])) {
            foreach ($costSummary['wastage_detail']['groups'] as $catName => $group) {
                $wastageByCat[] = [
                    'name'  => $catName,
                    'total' => $group['total'],
                ];
            }
        }

        // MoM comparison
        $momComparison = [
            'current' => [
                'revenue'     => $costSummary['totals']['revenue'],
                'cogs'        => $costSummary['totals']['cogs'],
                'cost_pct'    => $costSummary['totals']['cost_pct'],
                'wastage'     => $costSummary['totals']['wastage'],
                'staff_meals' => $costSummary['totals']['staff_meals'],
            ],
            'previous' => [
                'revenue'     => $prevSummary['totals']['revenue'],
                'cogs'        => $prevSummary['totals']['cogs'],
                'cost_pct'    => $prevSummary['totals']['cost_pct'],
                'wastage'     => $prevSummary['totals']['wastage'],
                'staff_meals' => $prevSummary['totals']['staff_meals'],
            ],
        ];

        $totalPax = array_sum(array_column($dailySales, 'pax'));
        $totalRevenue = $costSummary['totals']['revenue'];
        $avgCheck = $totalPax > 0 ? round($totalRevenue / $totalPax, 2) : 0;

        $prevTotals = $prevSummary['totals'];
        $curTotals = $costSummary['totals'];
        $revChange = $prevTotals['revenue'] > 0 ? round(($curTotals['revenue'] - $prevTotals['revenue']) / $prevTotals['revenue'] * 100, 1) : 0;
        $cogsChange = $prevTotals['cogs'] > 0 ? round(($curTotals['cogs'] - $prevTotals['cogs']) / $prevTotals['cogs'] * 100, 1) : 0;

        $this->dashboardData = [
            'cost_summary'    => $costSummary,
            'prev_summary'    => $prevSummary,
            'daily_sales'     => $dailySales,
            'day_of_week'     => $dayOfWeek,
            'top_wastage'     => $topWastage,
            'wastage_by_cat'  => $wastageByCat,
            'mom_comparison'  => $momComparison,
            'total_pax'       => $totalPax,
            'avg_check'       => $avgCheck,
            'rev_change'      => $revChange,
            'cogs_change'     => $cogsChange,
        ];
    }

    private function periodLabel(): string
    {
        if ($this->mode === 'weekly' && $this->weekStart) {
            return Carbon::parse($this->weekStart)->format('d M Y') . ' - ' . Carbon::parse($this->weekStart)->addDays(6)->format('d M Y');
        }

        return Carbon::createFromFormat('Y-m', $this->period)->format('F Y');
    }

    private function resolveOutletId(): ?int
    {
        return $this->outletId ?: null;
    }

    private function exportCostSummaryPdf($company, $outlet, $periodLabel)
    {
        if (empty($this->summary['categories'])) {
            return;
        }

        $summary = $this->summary;
        $mode = $this->mode;

        if ($this->mode === 'weekly') {
            $filename = "cost-summary-week-{$this->weekStart}.pdf";
        } else {
            $filename = "cost-summary-{$this->period}.pdf";
        }

        $pdf = Pdf::loadView('pdf.cost-summary', compact('summary', 'company', 'outlet', 'periodLabel', 'mode'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print($pdf->output()), $filename);
    }

    private function exportPerformancePdf($company, $outlet, $periodLabel)
    {
        $data = $this->dashboardData;
        $filename = "performance-report-{$this->period}.pdf";

        $pdf = Pdf::loadView('pdf.performance-report', compact('data', 'company', 'outlet', 'periodLabel'))
            ->setPaper('a4', 'portrait');

        return response()->streamDownload(fn () => print($pdf->output()), $filename);
    }

    private function exportCostAnalysisPdf($company, $outlet, $periodLabel)
    {
        $data = $this->dashboardData;
        $summary = $this->summary;
        $filename = "cost-analysis-{$this->period}.pdf";

        $pdf = Pdf::loadView('pdf.cost-analysis-report', compact('data', 'summary', 'company', 'outlet', 'periodLabel'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print($pdf->output()), $filename);
    }

    private function exportWastagePdf($company, $outlet, $periodLabel)
    {
        $data = $this->dashboardData;
        $summary = $this->summary;
        $filename = "wastage-report-{$this->period}.pdf";

        $pdf = Pdf::loadView('pdf.wastage-report', compact('data', 'summary', 'company', 'outlet', 'periodLabel'))
            ->setPaper('a4', 'portrait');

        return response()->streamDownload(fn () => print($pdf->output()), $filename);
    }
}
