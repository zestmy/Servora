<?php

namespace App\Services;

use App\Models\AiAnalysisLog;
use App\Models\AppSetting;
use App\Models\CalendarEvent;
use App\Models\SalesRecord;
use App\Models\SalesTarget;
use App\Models\WastageRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAnalyticsService
{
    private CostSummaryService $costService;

    public function __construct(CostSummaryService $costService)
    {
        $this->costService = $costService;
    }

    public function analyze(string $period, ?int $outletId, string $analysisType = 'monthly_review', ?string $customQuestion = null): array
    {
        $provider = AppSetting::get('ai_provider', 'anthropic');
        $apiKey = $this->resolveApiKey($provider);

        $context = $this->buildContext($period, $outletId);
        $prompt = $this->buildPrompt($context, $analysisType, $customQuestion);
        $promptHash = hash('sha256', $prompt);

        // Check cache (same prompt within last 24h)
        $cached = AiAnalysisLog::where('prompt_hash', $promptHash)
            ->where('created_at', '>=', now()->subDay())
            ->first();

        if ($cached) {
            return [
                'response'   => $cached->response_text,
                'cached'     => true,
                'tokens'     => ['input' => $cached->input_tokens, 'output' => $cached->output_tokens],
                'model'      => $cached->model_used,
                'created_at' => $cached->created_at->toDateTimeString(),
                'log_id'     => $cached->id,
            ];
        }

        $result = $provider === 'openrouter'
            ? $this->callOpenRouter($apiKey, $context, $prompt)
            : $this->callAnthropic($apiKey, $context, $prompt);

        // Store in log
        $log = AiAnalysisLog::create([
            'company_id'    => Auth::user()->company_id,
            'outlet_id'     => $outletId,
            'period'        => $period,
            'analysis_type' => $analysisType,
            'prompt_hash'   => $promptHash,
            'prompt_text'   => $prompt,
            'response_text' => $result['response'],
            'model_used'    => $result['model'],
            'input_tokens'  => $result['tokens']['input'],
            'output_tokens' => $result['tokens']['output'],
            'requested_by'  => Auth::id(),
        ]);

        return [
            'response'   => $result['response'],
            'cached'     => false,
            'tokens'     => $result['tokens'],
            'model'      => $result['model'],
            'log_id'     => $log->id,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    private function resolveApiKey(string $provider): string
    {
        if ($provider === 'openrouter') {
            $key = AppSetting::get('openrouter_api_key');
            if (empty($key)) {
                throw new \RuntimeException('OpenRouter API key not configured. Contact your system administrator.');
            }
            return $key;
        }

        $key = AppSetting::get('anthropic_api_key');
        if (empty($key)) {
            throw new \RuntimeException('Anthropic API key not configured. Contact your system administrator.');
        }
        return $key;
    }

    private function callAnthropic(string $apiKey, array $context, string $prompt): array
    {
        $model = 'claude-sonnet-4-5-20250514';

        $previousTimeout = ini_get('max_execution_time');
        set_time_limit(120);

        $response = Http::timeout(90)
            ->withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => $model,
                'max_tokens' => 4096,
                'system'     => $this->systemPrompt($context),
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            Log::error('Anthropic API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('AI analysis failed (HTTP ' . $response->status() . '). Please check your API key and try again.');
        }

        $data = $response->json();

        set_time_limit((int) $previousTimeout ?: 60);

        return [
            'response' => $data['content'][0]['text'] ?? '',
            'tokens'   => [
                'input'  => $data['usage']['input_tokens'] ?? null,
                'output' => $data['usage']['output_tokens'] ?? null,
            ],
            'model' => $model,
        ];
    }

    private function callOpenRouter(string $apiKey, array $context, string $prompt): array
    {
        $model = AppSetting::get('openrouter_model') ?: 'anthropic/claude-sonnet-4-5-20250514';

        $previousTimeout = ini_get('max_execution_time');
        set_time_limit(120);

        $response = Http::timeout(90)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => config('app.url', 'http://localhost'),
                'X-Title'       => config('app.name', 'Servora'),
            ])
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model'      => $model,
                'max_tokens' => 4096,
                'messages'   => [
                    ['role' => 'system', 'content' => $this->systemPrompt($context)],
                    ['role' => 'user',   'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            Log::error('OpenRouter API error', ['status' => $response->status(), 'body' => $response->body()]);
            $body = $response->json();
            $msg = $body['error']['message'] ?? ('HTTP ' . $response->status());
            throw new \RuntimeException('AI analysis failed via OpenRouter: ' . $msg);
        }

        $data = $response->json();
        $actualModel = $data['model'] ?? $model;

        set_time_limit((int) $previousTimeout ?: 60);

        return [
            'response' => $data['choices'][0]['message']['content'] ?? '',
            'tokens'   => [
                'input'  => $data['usage']['prompt_tokens'] ?? null,
                'output' => $data['usage']['completion_tokens'] ?? null,
            ],
            'model' => $actualModel,
        ];
    }

    public function buildContext(string $period, ?int $outletId): array
    {
        $date = Carbon::createFromFormat('Y-m', $period);
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        // P&L from existing service
        $costSummary = $this->costService->generate($period, $outletId);

        // Previous month for comparison
        $prevPeriod = $date->copy()->subMonth()->format('Y-m');
        $prevSummary = $this->costService->generate($prevPeriod, $outletId);

        // Daily sales breakdown
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
                'date'    => $row->sale_date->format('Y-m-d'),
                'day'     => $row->sale_date->format('l'),
                'revenue' => round((float) $row->revenue, 2),
                'pax'     => (int) $row->pax,
                'avg'     => $row->pax > 0 ? round((float) $row->revenue / $row->pax, 2) : 0,
            ])
            ->toArray();

        // Calendar events
        $events = CalendarEvent::forPeriod($period)
            ->when($outletId, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('outlet_id')->orWhere('outlet_id', $outletId)))
            ->orderBy('event_date')
            ->get()
            ->map(fn ($e) => [
                'date'     => $e->event_date->format('Y-m-d'),
                'end_date' => $e->end_date?->format('Y-m-d'),
                'title'    => $e->title,
                'category' => $e->categoryLabel(),
                'impact'   => $e->impact ?? 'neutral',
            ])
            ->toArray();

        // Wastage totals
        $wastageQuery = WastageRecord::whereBetween('wastage_date', [$startOfMonth, $endOfMonth]);
        if ($outletId) {
            $wastageQuery->where('outlet_id', $outletId);
        }
        $totalWastage = round((float) $wastageQuery->sum('total_cost'), 2);

        $company = Auth::user()->company;
        $outlet = $outletId ? \App\Models\Outlet::find($outletId) : null;

        // Sales target for this period
        $target = SalesTarget::where('period', $period)
            ->where(fn ($q) => $outletId
                ? $q->where('outlet_id', $outletId)->orWhereNull('outlet_id')
                : $q->whereNull('outlet_id'))
            ->orderByRaw('outlet_id IS NULL ASC')
            ->first();

        // Historical monthly totals (last 6 months for trend)
        $historicalSales = [];
        for ($i = 6; $i >= 1; $i--) {
            $histDate = $date->copy()->subMonths($i);
            $histStart = $histDate->copy()->startOfMonth();
            $histEnd = $histDate->copy()->endOfMonth();
            $histQuery = SalesRecord::whereBetween('sale_date', [$histStart, $histEnd]);
            if ($outletId) {
                $histQuery->where('outlet_id', $outletId);
            }
            $histRev = round((float) $histQuery->sum('total_revenue'), 2);
            $histPax = (int) $histQuery->sum('pax');
            if ($histRev > 0 || $histPax > 0) {
                $historicalSales[] = [
                    'period'  => $histDate->format('Y-m'),
                    'label'   => $histDate->format('M Y'),
                    'revenue' => $histRev,
                    'pax'     => $histPax,
                    'avg'     => $histPax > 0 ? round($histRev / $histPax, 2) : 0,
                ];
            }
        }

        return [
            'company_name'       => $company->name ?? 'Unknown',
            'outlet_name'        => $outlet?->name ?? 'All Outlets',
            'period'             => $period,
            'period_label'       => $date->format('F Y'),
            'cost_summary'       => $costSummary,
            'prev_summary'       => $prevSummary,
            'daily_sales'        => $dailySales,
            'events'             => $events,
            'total_wastage'      => $totalWastage,
            'wastage_detail'     => $costSummary['wastage_detail'] ?? ['groups' => [], 'total' => 0],
            'staff_meals_detail' => $costSummary['staff_meals_detail'] ?? ['groups' => [], 'total' => 0],
            'staff_meals_total'  => $costSummary['totals']['staff_meals'] ?? 0,
            'sales_target'       => $target ? [
                'revenue' => (float) $target->target_revenue,
                'pax'     => $target->target_pax,
                'notes'   => $target->notes,
            ] : null,
            'historical_sales'   => $historicalSales,
        ];
    }

    private function systemPrompt(array $context): string
    {
        return "You are an expert Food & Beverage operations analyst for \"{$context['company_name']}\" — outlet: \"{$context['outlet_name']}\". "
            . "You analyze operational data and provide actionable insights to help maximize revenue and profit. "
            . "Be specific with numbers, percentages, and comparisons. Identify patterns and correlations with calendar events. "
            . "Format your response with clear markdown headers (##). Use tables where helpful. "
            . "Keep language professional but concise. Focus on what's actionable.";
    }

    public function buildPrompt(array $context, string $analysisType, ?string $customQuestion): string
    {
        $sections = [];

        // Revenue summary
        $totals = $context['cost_summary']['totals'];
        $prevTotals = $context['prev_summary']['totals'];

        $sections[] = "## Period: {$context['period_label']}\n";

        // P&L overview
        $sections[] = "## Profit & Loss Summary";
        $sections[] = "| Metric | This Month | Previous Month | Change |";
        $sections[] = "|--------|-----------|---------------|--------|";

        $revChange = $prevTotals['revenue'] > 0 ? round(($totals['revenue'] - $prevTotals['revenue']) / $prevTotals['revenue'] * 100, 1) : 0;
        $sections[] = "| Revenue | RM " . number_format($totals['revenue'], 2) . " | RM " . number_format($prevTotals['revenue'], 2) . " | {$revChange}% |";

        $cogsChange = $prevTotals['cogs'] > 0 ? round(($totals['cogs'] - $prevTotals['cogs']) / $prevTotals['cogs'] * 100, 1) : 0;
        $sections[] = "| COGS | RM " . number_format($totals['cogs'], 2) . " | RM " . number_format($prevTotals['cogs'], 2) . " | {$cogsChange}% |";
        $sections[] = "| Cost % | {$totals['cost_pct']}% | {$prevTotals['cost_pct']}% | " . round($totals['cost_pct'] - $prevTotals['cost_pct'], 1) . "pp |";
        $sections[] = "| Purchases | RM " . number_format($totals['purchases'], 2) . " | RM " . number_format($prevTotals['purchases'], 2) . " | |";
        $sections[] = "| Wastage | RM " . number_format($context['total_wastage'], 2) . " | | |";
        $sections[] = "| Staff Meals | RM " . number_format($context['staff_meals_total'], 2) . " | | |";
        $sections[] = "";

        // Category breakdown with full details
        if (!empty($context['cost_summary']['categories'])) {
            $sections[] = "## Cost by Category (Detailed)";
            $sections[] = "| Category | Revenue | Purchases | Opening | Closing | COGS | Cost % |";
            $sections[] = "|----------|---------|-----------|---------|---------|------|--------|";
            foreach ($context['cost_summary']['categories'] as $cat) {
                $sections[] = "| {$cat['name']} | RM " . number_format($cat['revenue'], 2)
                    . " | RM " . number_format($cat['purchases'], 2)
                    . " | RM " . number_format($cat['opening_stock'], 2)
                    . " | RM " . number_format($cat['closing_stock'], 2)
                    . " | RM " . number_format($cat['cogs'], 2)
                    . " | {$cat['cost_pct']}% |";
            }
            $sections[] = "";

            // Purchase efficiency per category
            $sections[] = "## Purchase Efficiency by Category";
            $sections[] = "| Category | Revenue | Purchases | Purchase-to-Revenue Ratio |";
            $sections[] = "|----------|---------|-----------|--------------------------|";
            foreach ($context['cost_summary']['categories'] as $cat) {
                $ratio = $cat['revenue'] > 0 ? round($cat['purchases'] / $cat['revenue'] * 100, 1) : 0;
                $sections[] = "| {$cat['name']} | RM " . number_format($cat['revenue'], 2)
                    . " | RM " . number_format($cat['purchases'], 2)
                    . " | {$ratio}% |";
            }
            $sections[] = "";
        }

        // Wastage breakdown by item
        if (!empty($context['wastage_detail']['groups'])) {
            $sections[] = "## Wastage Breakdown (RM " . number_format($context['wastage_detail']['total'], 2) . " total)";
            $sections[] = "| Item | Category | Quantity | UOM | Cost (RM) |";
            $sections[] = "|------|----------|----------|-----|-----------|";
            foreach ($context['wastage_detail']['groups'] as $catName => $group) {
                foreach ($group['items'] as $item) {
                    $sections[] = "| {$item['name']} | {$catName} | " . number_format($item['quantity'], 2)
                        . " | {$item['uom']} | " . number_format($item['total_cost'], 2) . " |";
                }
            }
            $sections[] = "";
        }

        // Staff meals breakdown
        if (!empty($context['staff_meals_detail']['groups'])) {
            $sections[] = "## Staff Meals Breakdown (RM " . number_format($context['staff_meals_detail']['total'], 2) . " total)";
            $sections[] = "| Item | Category | Quantity | UOM | Cost (RM) |";
            $sections[] = "|------|----------|----------|-----|-----------|";
            foreach ($context['staff_meals_detail']['groups'] as $catName => $group) {
                foreach ($group['items'] as $item) {
                    $sections[] = "| {$item['name']} | {$catName} | " . number_format($item['quantity'], 2)
                        . " | {$item['uom']} | " . number_format($item['total_cost'], 2) . " |";
                }
            }
            $sections[] = "";
        }

        // Inventory summary
        if (!empty($context['cost_summary']['categories'])) {
            $hasStock = false;
            foreach ($context['cost_summary']['categories'] as $cat) {
                if ($cat['opening_stock'] > 0 || $cat['closing_stock'] > 0) {
                    $hasStock = true;
                    break;
                }
            }
            if ($hasStock) {
                $sections[] = "## Inventory Position";
                $sections[] = "| Category | Opening Stock | Closing Stock | Change | Transfer In | Transfer Out |";
                $sections[] = "|----------|--------------|---------------|--------|-------------|-------------|";
                foreach ($context['cost_summary']['categories'] as $cat) {
                    if ($cat['opening_stock'] > 0 || $cat['closing_stock'] > 0) {
                        $stockChange = $cat['closing_stock'] - $cat['opening_stock'];
                        $changeStr = ($stockChange >= 0 ? '+' : '') . number_format($stockChange, 2);
                        $sections[] = "| {$cat['name']} | RM " . number_format($cat['opening_stock'], 2)
                            . " | RM " . number_format($cat['closing_stock'], 2)
                            . " | {$changeStr} | RM " . number_format($cat['transfer_in'], 2)
                            . " | RM " . number_format($cat['transfer_out'], 2) . " |";
                    }
                }
                $sections[] = "";
            }
        }

        // Daily sales
        if (!empty($context['daily_sales'])) {
            $sections[] = "## Daily Sales Trend";
            $sections[] = "| Date | Day | Revenue | Pax | Avg/Pax |";
            $sections[] = "|------|-----|---------|-----|---------|";
            foreach ($context['daily_sales'] as $day) {
                $sections[] = "| {$day['date']} | {$day['day']} | RM " . number_format($day['revenue'], 2) . " | {$day['pax']} | RM " . number_format($day['avg'], 2) . " |";
            }
            $sections[] = "";

            // Day-of-week averages
            $dayAgg = [];
            foreach ($context['daily_sales'] as $day) {
                $name = $day['day'];
                if (!isset($dayAgg[$name])) {
                    $dayAgg[$name] = ['revenue' => 0, 'pax' => 0, 'count' => 0];
                }
                $dayAgg[$name]['revenue'] += $day['revenue'];
                $dayAgg[$name]['pax'] += $day['pax'];
                $dayAgg[$name]['count']++;
            }
            if (!empty($dayAgg)) {
                $sections[] = "## Day-of-Week Averages";
                $sections[] = "| Day | Avg Revenue | Avg Pax | Occurrences |";
                $sections[] = "|-----|------------|---------|------------|";
                foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $dayName) {
                    if (isset($dayAgg[$dayName])) {
                        $d = $dayAgg[$dayName];
                        $avgRev = round($d['revenue'] / $d['count'], 2);
                        $avgPax = round($d['pax'] / $d['count']);
                        $sections[] = "| {$dayName} | RM " . number_format($avgRev, 2) . " | {$avgPax} | {$d['count']} |";
                    }
                }
                $sections[] = "";
            }
        }

        // Calendar events
        if (!empty($context['events'])) {
            $sections[] = "## Calendar Events This Period";
            $sections[] = "| Date | Event | Category | Expected Impact |";
            $sections[] = "|------|-------|----------|----------------|";
            foreach ($context['events'] as $event) {
                $dateStr = $event['date'] . ($event['end_date'] ? " to {$event['end_date']}" : '');
                $sections[] = "| {$dateStr} | {$event['title']} | {$event['category']} | {$event['impact']} |";
            }
            $sections[] = "";
        }

        // Sales target
        if (!empty($context['sales_target'])) {
            $t = $context['sales_target'];
            $sections[] = "## Sales Target for {$context['period_label']}";
            $sections[] = "| Metric | Target |";
            $sections[] = "|--------|--------|";
            $sections[] = "| Revenue | RM " . number_format($t['revenue'], 2) . " |";
            if ($t['pax']) {
                $sections[] = "| Pax | " . number_format($t['pax']) . " |";
            }
            if ($t['notes']) {
                $sections[] = "| Notes | {$t['notes']} |";
            }

            // Achievement
            $actualRev = $totals['revenue'] ?? 0;
            $pct = $t['revenue'] > 0 ? round($actualRev / $t['revenue'] * 100, 1) : 0;
            $sections[] = "| Actual Revenue | RM " . number_format($actualRev, 2) . " ({$pct}% of target) |";
            $sections[] = "";
        }

        // Historical monthly sales (for trend & prediction)
        if (!empty($context['historical_sales'])) {
            $sections[] = "## Historical Monthly Sales (Last 6 Months)";
            $sections[] = "| Month | Revenue | Pax | Avg/Pax |";
            $sections[] = "|-------|---------|-----|---------|";
            foreach ($context['historical_sales'] as $hist) {
                $sections[] = "| {$hist['label']} | RM " . number_format($hist['revenue'], 2) . " | {$hist['pax']} | RM " . number_format($hist['avg'], 2) . " |";
            }
            $sections[] = "";
        }

        // Analysis instructions
        $sections[] = "## Analysis Request";

        switch ($analysisType) {
            case 'monthly_review':
                $sections[] = "Provide a comprehensive monthly operations review:\n"
                    . "1. **Revenue Performance** — overall trend, best/worst days, day-of-week patterns\n"
                    . "2. **Target Achievement** — if a sales target is set, how close are we? On track or behind? Projected end-of-month result\n"
                    . "3. **Cost Analysis** — cost % by category, areas of concern, month-over-month changes\n"
                    . "4. **Inventory Health** — stock movement, are closing values reasonable? Any overstocking or understocking signals?\n"
                    . "5. **Wastage & Staff Meals** — breakdown by category/item, are these within acceptable range? Top offenders?\n"
                    . "6. **Purchase Efficiency** — purchase-to-revenue ratios by category, any signs of overpurchasing?\n"
                    . "7. **Event Correlation** — how calendar events impacted sales (correlate event dates with daily revenue)\n"
                    . "8. **Historical Comparison** — compare this month to previous months' trend, is performance improving or declining?\n"
                    . "9. **Key Recommendations** — 3-5 specific, actionable steps to improve next month";
                break;

            case 'weekly_review':
                $weekDate = $customQuestion ?? now()->startOfWeek()->format('Y-m-d');
                $weekEnd = Carbon::parse($weekDate)->addDays(6)->format('Y-m-d');
                $sections[] = "Focus ONLY on the week of **{$weekDate} to {$weekEnd}** from the daily sales data above. Provide a concise weekly operations review:\n"
                    . "1. **Weekly Summary** — total revenue, total pax, and average check for this specific week\n"
                    . "2. **Day-by-Day Breakdown** — highlight the best and worst performing days and explain why\n"
                    . "3. **Comparison** — compare this week's daily averages to the overall monthly averages shown above\n"
                    . "4. **Cost & Wastage** — summarize cost position and any wastage items that occurred this week\n"
                    . "5. **Event Impact** — did any calendar events fall within or near this week? How did they affect performance?\n"
                    . "6. **Immediate Actions** — 2-3 quick wins or adjustments for the upcoming week based on what you see";
                break;

            case 'trend_analysis':
                $sections[] = "Focus on trend analysis:\n"
                    . "1. **Daily Revenue Patterns** — identify peak/low days, weekday vs weekend trends\n"
                    . "2. **Monthly Trend** — use historical monthly data to identify growth/decline trajectory\n"
                    . "3. **Pax & Average Check Trends** — are more people coming or spending more per visit?\n"
                    . "4. **Event Impact Analysis** — quantify revenue lift/drop around calendar events\n"
                    . "5. **Target Tracking** — if targets are set, plot progress and project achievement\n"
                    . "6. **Inventory Trends** — stock value changes, purchase patterns relative to sales\n"
                    . "7. **Forecasting** — based on historical patterns, what should next month look like?\n"
                    . "8. **Revenue Optimization** — specific suggestions for pricing, promotions, scheduling";
                break;

            case 'cost_optimization':
                $sections[] = "Focus on cost optimization:\n"
                    . "1. **Cost % Analysis** — which categories are over target? (Food < 35%, Beverage < 25%)\n"
                    . "2. **Purchase Efficiency** — are purchases aligned with revenue? Any overstocking?\n"
                    . "3. **Wastage Reduction** — specific items to focus on, cost impact, root cause analysis\n"
                    . "4. **Staff Meals Control** — are staff meal costs proportionate? Any items that should be restricted?\n"
                    . "5. **Inventory Optimization** — stock levels vs usage, dead stock indicators\n"
                    . "6. **Menu Engineering** — which categories should be promoted based on margin?\n"
                    . "7. **Action Plan** — ranked list of cost-saving opportunities with estimated impact";
                break;

            case 'predictive_analysis':
                $nextMonth = Carbon::createFromFormat('Y-m', $context['period'])->addMonth();
                $sections[] = "Based on ALL the data above — historical monthly trends, daily patterns, day-of-week averages, calendar events, cost trends, and sales targets — provide a **predictive sales forecast for {$nextMonth->format('F Y')}**:\n"
                    . "1. **Revenue Forecast** — provide low / expected / high estimates with reasoning\n"
                    . "2. **Pax Forecast** — predicted customer count based on trends\n"
                    . "3. **Daily Revenue Estimate** — expected average daily revenue and best/worst performing days of week\n"
                    . "4. **Target Achievement** — if a sales target is set, predict likelihood of hitting it and by when\n"
                    . "5. **Growth Trend** — month-over-month revenue trajectory, is the business growing or declining?\n"
                    . "6. **Seasonality & Events** — how upcoming events or seasonal patterns will impact next month\n"
                    . "7. **Risk Factors** — potential threats to revenue (weather, competition, economic conditions)\n"
                    . "8. **Recommendations** — 3-5 specific actions to maximize next month's performance\n"
                    . "9. **Confidence Level** — rate your prediction confidence (Low/Medium/High) based on data availability";
                break;

            case 'custom':
                $sections[] = $customQuestion ?? 'Provide a general analysis of the data above.';
                break;
        }

        return implode("\n", $sections);
    }
}
