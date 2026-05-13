<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\SalesRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyticsDataService
{
    /**
     * Get daily sales data for a specific date and outlet.
     */
    public function getDailySalesData(int $companyId, ?int $outletId, Carbon $date): array
    {
        $query = SalesRecord::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('sale_date', $date);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $today = $query->selectRaw('
            COALESCE(SUM(total_revenue), 0) as revenue,
            COALESCE(SUM(pax), 0) as pax,
            COALESCE(SUM(transactions), 0) as transactions,
            COALESCE(SUM(discount_amount), 0) as discounts,
            COALESCE(SUM(tax_amount), 0) as tax,
            COALESCE(SUM(service_charges), 0) as service_charges
        ')->first();

        // Yesterday
        $yesterday = $this->getSalesForDate($companyId, $outletId, $date->copy()->subDay());

        // Same day last week
        $lastWeek = $this->getSalesForDate($companyId, $outletId, $date->copy()->subWeek());

        // Same day last year
        $lastYear = $this->getSalesForDate($companyId, $outletId, $date->copy()->subYear());

        // By meal period
        $byMealPeriod = $this->getSalesByMealPeriod($companyId, $outletId, $date);

        // Top items (from sales lines)
        $topItems = $this->getTopItems($companyId, $outletId, $date, $date);

        // Calculate metrics
        $avgPerPax = $today->pax > 0 ? round($today->revenue / $today->pax, 2) : 0;
        $avgPerTransaction = $today->transactions > 0 ? round($today->revenue / $today->transactions, 2) : 0;

        return [
            'date' => $date->toDateString(),
            'outlet_id' => $outletId,
            'today' => [
                'revenue' => (float) $today->revenue,
                'pax' => (int) $today->pax,
                'transactions' => (int) $today->transactions,
                'discounts' => (float) $today->discounts,
                'tax' => (float) $today->tax,
                'service_charges' => (float) $today->service_charges,
                'avg_per_pax' => $avgPerPax,
                'avg_per_transaction' => $avgPerTransaction,
            ],
            'comparisons' => [
                'yesterday' => $this->buildComparison($today->revenue, $yesterday['revenue']),
                'last_week' => $this->buildComparison($today->revenue, $lastWeek['revenue']),
                'last_year' => $this->buildComparison($today->revenue, $lastYear['revenue']),
            ],
            'by_meal_period' => $byMealPeriod,
            'top_items' => $topItems,
        ];
    }

    /**
     * Get weekly sales data.
     */
    public function getWeeklySalesData(int $companyId, ?int $outletId, Carbon $weekStart): array
    {
        // Ensure we're working with Monday-Sunday weeks for business reporting
        $weekStartDate = $weekStart->copy()->startOfWeek(Carbon::MONDAY);
        $weekEndDate = $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY);

        Log::info('Weekly report date range', [
            'input_date' => $weekStart->toDateString(),
            'week_start' => $weekStartDate->toDateString(),
            'week_end' => $weekEndDate->toDateString(),
            'company_id' => $companyId,
            'outlet_id' => $outletId,
        ]);

        // This week's data by day
        $dailyData = $this->getSalesByDay($companyId, $outletId, $weekStartDate, $weekEndDate);

        // This week totals
        $thisWeek = $this->getSalesForPeriod($companyId, $outletId, $weekStartDate, $weekEndDate);

        Log::info('Weekly report results', [
            'this_week_revenue' => $thisWeek['revenue'],
            'this_week_pax' => $thisWeek['pax'],
            'daily_data_count' => count($dailyData),
        ]);

        // Last week
        $lastWeekStart = $weekStartDate->copy()->subWeek();
        $lastWeekEnd = $weekEndDate->copy()->subWeek();
        $lastWeek = $this->getSalesForPeriod($companyId, $outletId, $lastWeekStart, $lastWeekEnd);

        // Same week last year
        $lastYearStart = $weekStartDate->copy()->subYear();
        $lastYearEnd = $weekEndDate->copy()->subYear();
        $lastYear = $this->getSalesForPeriod($companyId, $outletId, $lastYearStart, $lastYearEnd);

        // By meal period for the week
        $byMealPeriod = $this->getSalesByMealPeriodForPeriod($companyId, $outletId, $weekStartDate, $weekEndDate);

        // Top items
        $topItems = $this->getTopItems($companyId, $outletId, $weekStartDate, $weekEndDate, 10);

        // Best and worst days
        $bestDay = collect($dailyData)->sortByDesc('revenue')->first();
        $worstDay = collect($dailyData)->where('revenue', '>', 0)->sortBy('revenue')->first();

        return [
            'period_start' => $weekStartDate->toDateString(),
            'period_end' => $weekEndDate->toDateString(),
            'outlet_id' => $outletId,
            'this_week' => $thisWeek,
            'daily_breakdown' => $dailyData,
            'comparisons' => [
                'last_week' => $this->buildComparison($thisWeek['revenue'], $lastWeek['revenue']),
                'last_year' => $this->buildComparison($thisWeek['revenue'], $lastYear['revenue']),
            ],
            'by_meal_period' => $byMealPeriod,
            'top_items' => $topItems,
            'best_day' => $bestDay,
            'worst_day' => $worstDay,
        ];
    }

    /**
     * Get monthly sales data.
     */
    public function getMonthlySalesData(int $companyId, ?int $outletId, Carbon $monthStart): array
    {
        // Ensure we're working with the full month
        $monthStartDate = $monthStart->copy()->startOfMonth();
        $monthEndDate = $monthStartDate->copy()->endOfMonth();

        // Daily data for the month
        $dailyData = $this->getSalesByDay($companyId, $outletId, $monthStartDate, $monthEndDate);

        // Weekly breakdown
        $weeklyData = $this->getSalesByWeek($companyId, $outletId, $monthStartDate, $monthEndDate);

        // This month totals
        $thisMonth = $this->getSalesForPeriod($companyId, $outletId, $monthStartDate, $monthEndDate);

        // Last month
        $lastMonthStart = $monthStartDate->copy()->subMonth();
        $lastMonthEnd = $lastMonthStart->copy()->endOfMonth();
        $lastMonth = $this->getSalesForPeriod($companyId, $outletId, $lastMonthStart, $lastMonthEnd);

        // Same month last year
        $lastYearStart = $monthStartDate->copy()->subYear();
        $lastYearEnd = $lastYearStart->copy()->endOfMonth();
        $lastYear = $this->getSalesForPeriod($companyId, $outletId, $lastYearStart, $lastYearEnd);

        // By meal period
        $byMealPeriod = $this->getSalesByMealPeriodForPeriod($companyId, $outletId, $monthStartDate, $monthEndDate);

        // Top items
        $topItems = $this->getTopItems($companyId, $outletId, $monthStartDate, $monthEndDate, 15);

        return [
            'period_start' => $monthStartDate->toDateString(),
            'period_end' => $monthEndDate->toDateString(),
            'outlet_id' => $outletId,
            'this_month' => $thisMonth,
            'daily_breakdown' => $dailyData,
            'weekly_breakdown' => $weeklyData,
            'comparisons' => [
                'last_month' => $this->buildComparison($thisMonth['revenue'], $lastMonth['revenue']),
                'last_year' => $this->buildComparison($thisMonth['revenue'], $lastYear['revenue']),
            ],
            'by_meal_period' => $byMealPeriod,
            'top_items' => $topItems,
        ];
    }

    /**
     * Get outlet comparison data.
     */
    public function getOutletComparison(int $companyId, Carbon $startDate, Carbon $endDate): array
    {
        $outlets = Outlet::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        $comparison = [];

        foreach ($outlets as $outlet) {
            $data = $this->getSalesForPeriod($companyId, $outlet->id, $startDate, $endDate);
            $comparison[] = [
                'outlet_id' => $outlet->id,
                'outlet_name' => $outlet->name,
                'revenue' => $data['revenue'],
                'pax' => $data['pax'],
                'transactions' => $data['transactions'],
                'avg_per_pax' => $data['avg_per_pax'],
            ];
        }

        return collect($comparison)->sortByDesc('revenue')->values()->all();
    }

    // ── Helper Methods ───────────────────────────────────────────────────────

    protected function getSalesForDate(int $companyId, ?int $outletId, Carbon $date): array
    {
        $query = SalesRecord::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('sale_date', $date);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $result = $query->selectRaw('
            COALESCE(SUM(total_revenue), 0) as revenue,
            COALESCE(SUM(pax), 0) as pax,
            COALESCE(SUM(transactions), 0) as transactions
        ')->first();

        return [
            'revenue' => (float) $result->revenue,
            'pax' => (int) $result->pax,
            'transactions' => (int) $result->transactions,
        ];
    }

    protected function getSalesForPeriod(int $companyId, ?int $outletId, Carbon $start, Carbon $end): array
    {
        $query = SalesRecord::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('sale_date', [$start->toDateString(), $end->toDateString()]);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        // Debug: count records in this period
        $recordCount = (clone $query)->count();
        Log::info('getSalesForPeriod query', [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'company_id' => $companyId,
            'outlet_id' => $outletId,
            'record_count' => $recordCount,
        ]);

        $result = $query->selectRaw('
            COALESCE(SUM(total_revenue), 0) as revenue,
            COALESCE(SUM(pax), 0) as pax,
            COALESCE(SUM(transactions), 0) as transactions,
            COALESCE(SUM(discount_amount), 0) as discounts,
            COUNT(DISTINCT sale_date) as days_with_sales
        ')->first();

        $avgPerPax = $result->pax > 0 ? round($result->revenue / $result->pax, 2) : 0;

        return [
            'revenue' => (float) $result->revenue,
            'pax' => (int) $result->pax,
            'transactions' => (int) $result->transactions,
            'discounts' => (float) $result->discounts,
            'days_with_sales' => (int) $result->days_with_sales,
            'avg_per_pax' => $avgPerPax,
            'avg_daily_revenue' => $result->days_with_sales > 0
                ? round($result->revenue / $result->days_with_sales, 2)
                : 0,
        ];
    }

    protected function getSalesByDay(int $companyId, ?int $outletId, Carbon $start, Carbon $end): array
    {
        $query = SalesRecord::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('sale_date', [$start->toDateString(), $end->toDateString()]);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $results = $query->selectRaw('
            sale_date,
            SUM(total_revenue) as revenue,
            SUM(pax) as pax,
            SUM(transactions) as transactions
        ')
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();

        return $results->map(function ($row) {
            return [
                'date' => $row->sale_date->toDateString(),
                'day_name' => $row->sale_date->format('l'),
                'revenue' => (float) $row->revenue,
                'pax' => (int) $row->pax,
                'transactions' => (int) $row->transactions,
            ];
        })->all();
    }

    protected function getSalesByWeek(int $companyId, ?int $outletId, Carbon $start, Carbon $end): array
    {
        $query = SalesRecord::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('sale_date', [$start->toDateString(), $end->toDateString()]);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $results = $query->selectRaw('
            YEARWEEK(sale_date, 1) as year_week,
            MIN(sale_date) as week_start,
            SUM(total_revenue) as revenue,
            SUM(pax) as pax
        ')
            ->groupByRaw('YEARWEEK(sale_date, 1)')
            ->orderBy('year_week')
            ->get();

        return $results->map(function ($row) {
            return [
                'week_start' => Carbon::parse($row->week_start)->toDateString(),
                'revenue' => (float) $row->revenue,
                'pax' => (int) $row->pax,
            ];
        })->all();
    }

    protected function getSalesByMealPeriod(int $companyId, ?int $outletId, Carbon $date): array
    {
        $query = SalesRecord::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('sale_date', $date);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $results = $query->selectRaw('
            meal_period,
            SUM(total_revenue) as revenue,
            SUM(pax) as pax
        ')
            ->groupBy('meal_period')
            ->get();

        $total = $results->sum('revenue');

        return $results->map(function ($row) use ($total) {
            return [
                'meal_period' => $row->meal_period ?? 'all_day',
                'label' => SalesRecord::mealPeriodOptions()[$row->meal_period ?? 'all_day'] ?? ucfirst($row->meal_period ?? 'All Day'),
                'revenue' => (float) $row->revenue,
                'pax' => (int) $row->pax,
                'percentage' => $total > 0 ? round(($row->revenue / $total) * 100, 1) : 0,
            ];
        })->all();
    }

    protected function getSalesByMealPeriodForPeriod(int $companyId, ?int $outletId, Carbon $start, Carbon $end): array
    {
        $query = SalesRecord::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('sale_date', [$start->toDateString(), $end->toDateString()]);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $results = $query->selectRaw('
            meal_period,
            SUM(total_revenue) as revenue,
            SUM(pax) as pax
        ')
            ->groupBy('meal_period')
            ->get();

        $total = $results->sum('revenue');

        return $results->map(function ($row) use ($total) {
            return [
                'meal_period' => $row->meal_period ?? 'all_day',
                'label' => SalesRecord::mealPeriodOptions()[$row->meal_period ?? 'all_day'] ?? ucfirst($row->meal_period ?? 'All Day'),
                'revenue' => (float) $row->revenue,
                'pax' => (int) $row->pax,
                'percentage' => $total > 0 ? round(($row->revenue / $total) * 100, 1) : 0,
            ];
        })->all();
    }

    protected function getTopItems(int $companyId, ?int $outletId, Carbon $start, Carbon $end, int $limit = 5): array
    {
        $query = DB::table('sales_record_lines')
            ->join('sales_records', 'sales_record_lines.sales_record_id', '=', 'sales_records.id')
            ->where('sales_records.company_id', $companyId)
            ->whereBetween('sales_records.sale_date', [$start->toDateString(), $end->toDateString()])
            ->whereNull('sales_records.deleted_at');

        if ($outletId) {
            $query->where('sales_records.outlet_id', $outletId);
        }

        return $query->selectRaw('
                sales_record_lines.item_name,
                SUM(sales_record_lines.quantity) as total_qty,
                SUM(sales_record_lines.total_revenue) as total_revenue
            ')
            ->groupBy('sales_record_lines.item_name')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'name' => $row->item_name,
                    'quantity' => (int) $row->total_qty,
                    'revenue' => (float) $row->total_revenue,
                ];
            })
            ->all();
    }

    protected function buildComparison(float $current, float $previous): array
    {
        $change = $previous > 0
            ? round((($current - $previous) / $previous) * 100, 1)
            : ($current > 0 ? 100 : 0);

        return [
            'previous' => $previous,
            'change_percent' => $change,
            'change_amount' => round($current - $previous, 2),
            'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
        ];
    }
}
