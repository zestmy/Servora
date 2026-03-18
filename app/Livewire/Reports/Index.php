<?php

namespace App\Livewire\Reports;

use App\Models\Company;
use App\Models\LabourCost;
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

    public string $activeTab = 'cost_summary'; // cost_summary | performance | cost_analysis | wastage | labour_cost
    public string $period = '';
    public string $mode = 'monthly'; // monthly | weekly
    public string $weekStart = '';
    public ?int $outletId = null;
    public bool $compareMode = false;
    public string $compareTillDate = '';
    public array $summary = [];
    public array $dashboardData = [];
    public array $comparisonData = [];
    public array $monthlySalesByYear = [];
    public array $labourData = [];

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
        $this->weekStart = now()->startOfWeek()->format('Y-m-d');
        $this->compareTillDate = now()->format('Y-m-d');
        $this->outletId = $this->activeOutletId();
        $this->loadData();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function updatedPeriod(): void
    {
        // Sync compareTillDate to end of new month (or today if current month)
        $periodDate = Carbon::createFromFormat('Y-m', $this->period);
        if ($periodDate->format('Y-m') === now()->format('Y-m')) {
            $this->compareTillDate = now()->format('Y-m-d');
        } else {
            $this->compareTillDate = $periodDate->copy()->endOfMonth()->format('Y-m-d');
        }
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

    public function updatedCompareMode(): void
    {
        $this->loadData();
    }

    public function updatedCompareTillDate(): void
    {
        if ($this->compareMode) {
            $this->loadData();
        }
    }

    public function toggleCompare(): void
    {
        $this->compareMode = !$this->compareMode;
        if ($this->compareMode && !$this->compareTillDate) {
            $this->compareTillDate = now()->format('Y-m-d');
        }
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
        $this->loadMonthlySalesByYear();
        $this->loadLabourData();

        if ($this->compareMode && $this->mode === 'monthly') {
            $this->loadComparisonData();
        } else {
            $this->comparisonData = [];
        }
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

    private function loadComparisonData(): void
    {
        $service = new CostSummaryService();
        $outletId = $this->outletId ?: null;

        $currentDate = Carbon::createFromFormat('Y-m', $this->period);

        // MTD day = custom till date if set, otherwise today (for current month) or end of month
        if ($this->compareTillDate) {
            $tillDate = Carbon::parse($this->compareTillDate);
            // Clamp to selected month
            if ($tillDate->format('Y-m') === $currentDate->format('Y-m')) {
                $mtdDay = $tillDate->day;
            } else {
                $mtdDay = $currentDate->copy()->endOfMonth()->day;
            }
        } elseif ($currentDate->format('Y-m') === now()->format('Y-m')) {
            $mtdDay = now()->day;
        } else {
            $mtdDay = $currentDate->copy()->endOfMonth()->day;
        }

        // Period 1: This month MTD (1st to mtdDay)
        $curStart = $currentDate->copy()->startOfMonth();
        $curEnd = $currentDate->copy()->startOfMonth()->addDays($mtdDay - 1)->endOfDay();

        // Period 2: Last month MTD (1st to min(mtdDay, last day of prev month))
        $prevMonth = $currentDate->copy()->subMonth();
        $prevMtdDay = min($mtdDay, $prevMonth->copy()->endOfMonth()->day);
        $prevStart = $prevMonth->copy()->startOfMonth();
        $prevEnd = $prevMonth->copy()->startOfMonth()->addDays($prevMtdDay - 1)->endOfDay();

        // Period 3: Last year same month MTD
        $lastYear = $currentDate->copy()->subYear();
        $lyMtdDay = min($mtdDay, $lastYear->copy()->endOfMonth()->day);
        $lyStart = $lastYear->copy()->startOfMonth();
        $lyEnd = $lastYear->copy()->startOfMonth()->addDays($lyMtdDay - 1)->endOfDay();

        $curSummary = $service->generate(
            $curStart->format('Y-m'), $outletId,
            $curStart->format('Y-m-d'), $curEnd->format('Y-m-d')
        );
        $prevSummary = $service->generate(
            $prevStart->format('Y-m'), $outletId,
            $prevStart->format('Y-m-d'), $prevEnd->format('Y-m-d')
        );
        $lySummary = $service->generate(
            $lyStart->format('Y-m'), $outletId,
            $lyStart->format('Y-m-d'), $lyEnd->format('Y-m-d')
        );

        // Daily sales for pax/avg check per period
        $periods = [
            'current' => [$curStart, $curEnd, $curSummary],
            'prev_month' => [$prevStart, $prevEnd, $prevSummary],
            'prev_year' => [$lyStart, $lyEnd, $lySummary],
        ];

        $comparison = [];
        foreach ($periods as $key => [$start, $end, $summary]) {
            $salesQuery = SalesRecord::whereBetween('sale_date', [$start, $end]);
            if ($outletId) {
                $salesQuery->where('outlet_id', $outletId);
            }
            $pax = (int) $salesQuery->sum('pax');
            $revenue = $summary['totals']['revenue'];
            $avgCheck = $pax > 0 ? round($revenue / $pax, 2) : 0;

            $comparison[$key] = [
                'summary' => $summary,
                'pax' => $pax,
                'avg_check' => $avgCheck,
                'label' => $start->format('d M') . ' - ' . $end->format('d M Y'),
                'period_label' => $start->format('M Y'),
                'mtd_day' => $start->format('Y-m') === $curStart->format('Y-m') ? $mtdDay : ($key === 'prev_month' ? $prevMtdDay : $lyMtdDay),
            ];
        }

        // Calculate variances
        $curRev = $comparison['current']['summary']['totals']['revenue'];
        $prevRev = $comparison['prev_month']['summary']['totals']['revenue'];
        $lyRev = $comparison['prev_year']['summary']['totals']['revenue'];

        $comparison['var_vs_prev'] = [
            'revenue' => $prevRev > 0 ? round(($curRev - $prevRev) / $prevRev * 100, 1) : 0,
            'cogs' => $comparison['prev_month']['summary']['totals']['cogs'] > 0
                ? round(($comparison['current']['summary']['totals']['cogs'] - $comparison['prev_month']['summary']['totals']['cogs']) / abs($comparison['prev_month']['summary']['totals']['cogs']) * 100, 1)
                : 0,
            'pax' => $comparison['prev_month']['pax'] > 0
                ? round(($comparison['current']['pax'] - $comparison['prev_month']['pax']) / $comparison['prev_month']['pax'] * 100, 1)
                : 0,
        ];

        $comparison['var_vs_ly'] = [
            'revenue' => $lyRev > 0 ? round(($curRev - $lyRev) / $lyRev * 100, 1) : 0,
            'cogs' => $comparison['prev_year']['summary']['totals']['cogs'] > 0
                ? round(($comparison['current']['summary']['totals']['cogs'] - $comparison['prev_year']['summary']['totals']['cogs']) / abs($comparison['prev_year']['summary']['totals']['cogs']) * 100, 1)
                : 0,
            'pax' => $comparison['prev_year']['pax'] > 0
                ? round(($comparison['current']['pax'] - $comparison['prev_year']['pax']) / $comparison['prev_year']['pax'] * 100, 1)
                : 0,
        ];

        $this->comparisonData = $comparison;
    }

    private function loadMonthlySalesByYear(): void
    {
        $outletId = $this->outletId ?: null;

        $query = SalesRecord::selectRaw('YEAR(sale_date) as yr, MONTH(sale_date) as mo, SUM(total_revenue) as revenue, SUM(pax) as pax')
            ->groupByRaw('YEAR(sale_date), MONTH(sale_date)')
            ->orderByRaw('YEAR(sale_date), MONTH(sale_date)');

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $rows = $query->get();

        // Build: years[], data[year][month] = {revenue, pax}
        $years = $rows->pluck('yr')->unique()->sort()->values()->toArray();
        $data = [];
        $yearTotals = [];

        foreach ($years as $yr) {
            $yearTotals[$yr] = ['revenue' => 0, 'pax' => 0];
            for ($m = 1; $m <= 12; $m++) {
                $data[$yr][$m] = ['revenue' => 0, 'pax' => 0];
            }
        }

        foreach ($rows as $row) {
            $data[$row->yr][$row->mo] = [
                'revenue' => round((float) $row->revenue, 2),
                'pax'     => (int) $row->pax,
            ];
            $yearTotals[$row->yr]['revenue'] += round((float) $row->revenue, 2);
            $yearTotals[$row->yr]['pax'] += (int) $row->pax;
        }

        $this->monthlySalesByYear = [
            'years'       => $years,
            'data'        => $data,
            'year_totals' => $yearTotals,
        ];
    }

    private function loadLabourData(): void
    {
        $month = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()->toDateString();
        $outletId = $this->outletId ?: null;

        $query = LabourCost::where('month', $month)->with('allowances');
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $records = $query->get();

        // Revenue for the period
        $revenueQuery = SalesRecord::whereYear('sale_date', Carbon::parse($month)->year)
            ->whereMonth('sale_date', Carbon::parse($month)->month);
        if ($outletId) {
            $revenueQuery->where('outlet_id', $outletId);
        }

        // Per-outlet revenue
        $revenueByOutlet = (clone $revenueQuery)
            ->selectRaw('outlet_id, SUM(total_revenue) as revenue')
            ->groupBy('outlet_id')
            ->pluck('revenue', 'outlet_id');

        $totalRevenue = (float) $revenueQuery->sum('total_revenue');

        // Group by outlet
        $outlets = [];
        foreach ($records as $rec) {
            $oid = $rec->outlet_id;
            if (!isset($outlets[$oid])) {
                $outlets[$oid] = [
                    'outlet_name' => $rec->outlet->name ?? 'Unknown',
                    'revenue'     => (float) ($revenueByOutlet[$oid] ?? 0),
                    'foh'         => null,
                    'boh'         => null,
                ];
            }

            $totalAllowances = (float) $rec->allowances->sum('amount');
            $totalCost = (float) $rec->basic_salary + (float) $rec->service_point
                + (float) $rec->epf + (float) $rec->eis + (float) $rec->socso + $totalAllowances;

            $outlets[$oid][$rec->department_type] = [
                'basic_salary'    => (float) $rec->basic_salary,
                'service_point'   => (float) $rec->service_point,
                'allowances'      => $rec->allowances->map(fn ($a) => ['label' => $a->label, 'amount' => (float) $a->amount])->toArray(),
                'total_allowances' => $totalAllowances,
                'epf'             => (float) $rec->epf,
                'eis'             => (float) $rec->eis,
                'socso'           => (float) $rec->socso,
                'total'           => $totalCost,
            ];
        }

        // Totals
        $totalFoh = 0;
        $totalBoh = 0;
        foreach ($outlets as &$o) {
            $o['foh_total'] = $o['foh']['total'] ?? 0;
            $o['boh_total'] = $o['boh']['total'] ?? 0;
            $o['total']     = $o['foh_total'] + $o['boh_total'];
            $o['labour_pct'] = $o['revenue'] > 0 ? round($o['total'] / $o['revenue'] * 100, 1) : 0;
            $totalFoh += $o['foh_total'];
            $totalBoh += $o['boh_total'];
        }
        unset($o);

        $grandTotal = $totalFoh + $totalBoh;

        $this->labourData = [
            'outlets'       => $outlets,
            'total_foh'     => $totalFoh,
            'total_boh'     => $totalBoh,
            'grand_total'   => $grandTotal,
            'total_revenue' => $totalRevenue,
            'labour_pct'    => $totalRevenue > 0 ? round($grandTotal / $totalRevenue * 100, 1) : 0,
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
        $compareMode = $this->compareMode;
        $comparisonData = $this->comparisonData;

        if ($this->mode === 'weekly') {
            $filename = "cost-summary-week-{$this->weekStart}.pdf";
        } else {
            $filename = "cost-summary-{$this->period}.pdf";
        }

        $pdf = Pdf::loadView('pdf.cost-summary', compact('summary', 'company', 'outlet', 'periodLabel', 'mode', 'compareMode', 'comparisonData'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print($pdf->output()), $filename);
    }

    private function exportPerformancePdf($company, $outlet, $periodLabel)
    {
        $data = $this->dashboardData;
        $compareMode = $this->compareMode;
        $comparisonData = $this->comparisonData;
        $filename = "performance-report-{$this->period}.pdf";

        $pdf = Pdf::loadView('pdf.performance-report', compact('data', 'company', 'outlet', 'periodLabel', 'compareMode', 'comparisonData'))
            ->setPaper('a4', 'portrait');

        return response()->streamDownload(fn () => print($pdf->output()), $filename);
    }

    private function exportCostAnalysisPdf($company, $outlet, $periodLabel)
    {
        $data = $this->dashboardData;
        $summary = $this->summary;
        $compareMode = $this->compareMode;
        $comparisonData = $this->comparisonData;
        $filename = "cost-analysis-{$this->period}.pdf";

        $pdf = Pdf::loadView('pdf.cost-analysis-report', compact('data', 'summary', 'company', 'outlet', 'periodLabel', 'compareMode', 'comparisonData'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print($pdf->output()), $filename);
    }

    private function exportWastagePdf($company, $outlet, $periodLabel)
    {
        $data = $this->dashboardData;
        $summary = $this->summary;
        $compareMode = $this->compareMode;
        $comparisonData = $this->comparisonData;
        $filename = "wastage-report-{$this->period}.pdf";

        $pdf = Pdf::loadView('pdf.wastage-report', compact('data', 'summary', 'company', 'outlet', 'periodLabel', 'compareMode', 'comparisonData'))
            ->setPaper('a4', 'portrait');

        return response()->streamDownload(fn () => print($pdf->output()), $filename);
    }
}
