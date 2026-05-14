<?php

namespace App\Services;

use App\Models\AiAnalysisLog;
use App\Models\AppSetting;
use App\Models\CalendarEvent;
use App\Models\IngredientPriceHistory;
use App\Models\LabourCost;
use App\Models\OvertimeClaim;
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
        $apiKey = AppSetting::get('openrouter_api_key');
        if (empty($apiKey)) {
            throw new \RuntimeException('OpenRouter API key not configured. Go to Settings > API Keys.');
        }

        // For weekly review, pass the week start date to buildContext
        $weekStart = null;
        if ($analysisType === 'weekly_review' && $customQuestion) {
            $weekStart = $customQuestion;
        }

        $context = $this->buildContext($period, $outletId, $weekStart);
        $prompt = $this->buildPrompt($context, $analysisType, $customQuestion);
        $promptHash = hash('sha256', $prompt);

        // Check cache (same prompt within last 24h)
        $cached = AiAnalysisLog::where('prompt_hash', $promptHash)
            ->where('created_at', '>=', now()->subDay())
            ->first();

        if ($cached) {
            return [
                'response'   => $cached->response_text,
                'insights'   => $this->parseInsights($cached->response_text),
                'cached'     => true,
                'tokens'     => ['input' => $cached->input_tokens, 'output' => $cached->output_tokens],
                'model'      => $cached->model_used,
                'created_at' => $cached->created_at->toDateTimeString(),
                'log_id'     => $cached->id,
                'context'    => $context,
            ];
        }

        $result = $this->callOpenRouter($apiKey, $context, $prompt);

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
            'insights'   => $this->parseInsights($result['response']),
            'cached'     => false,
            'tokens'     => $result['tokens'],
            'model'      => $result['model'],
            'log_id'     => $log->id,
            'created_at' => now()->toDateTimeString(),
            'context'    => $context,
        ];
    }

    private function callOpenRouter(string $apiKey, array $context, string $prompt): array
    {
        $model = AppSetting::get('openrouter_model') ?: 'anthropic/claude-sonnet-4';

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
            throw new \RuntimeException('AI analysis failed: ' . $msg);
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

    /**
     * Parse AI response to extract structured insights.
     */
    private function parseInsights(string $content): ?array
    {
        $content = trim($content);

        // Try to extract JSON from markdown code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        // Try to find JSON object
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $json = $matches[0];
            $parsed = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }

        // If JSON parsing fails, return null (will fall back to markdown rendering)
        return null;
    }

    public function buildContext(string $period, ?int $outletId, ?string $weekStart = null): array
    {
        $date = Carbon::createFromFormat('!Y-m', $period);
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $company = Auth::user()->company;
        $outlet = $outletId ? \App\Models\Outlet::find($outletId) : null;

        // Get all active outlets for multi-outlet analysis
        $outlets = \App\Models\Outlet::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $isMultiOutlet = !$outletId && $outlets->count() > 1;

        // P&L from existing service
        $costSummary = $this->costService->generate($period, $outletId);

        // Previous month for comparison - store label for clarity
        $prevPeriod = $date->copy()->subMonth()->format('Y-m');
        $prevPeriodLabel = $date->copy()->subMonth()->format('F Y');
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

        // Per-outlet breakdown when viewing all outlets
        $outletsData = [];
        if ($isMultiOutlet) {
            foreach ($outlets as $o) {
                $outletRevenue = SalesRecord::where('outlet_id', $o->id)
                    ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
                    ->sum('total_revenue');
                $outletPax = SalesRecord::where('outlet_id', $o->id)
                    ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
                    ->sum('pax');
                $outletTrans = SalesRecord::where('outlet_id', $o->id)
                    ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
                    ->count();

                // Previous period for comparison
                $prevStart = $date->copy()->subMonth()->startOfMonth();
                $prevEnd = $date->copy()->subMonth()->endOfMonth();
                $prevRevenue = SalesRecord::where('outlet_id', $o->id)
                    ->whereBetween('sale_date', [$prevStart, $prevEnd])
                    ->sum('total_revenue');

                $change = $prevRevenue > 0
                    ? round(($outletRevenue - $prevRevenue) / $prevRevenue * 100, 1)
                    : 0;

                $outletsData[] = [
                    'outlet_id'   => $o->id,
                    'outlet_name' => $o->name,
                    'revenue'     => round((float) $outletRevenue, 2),
                    'pax'         => (int) $outletPax,
                    'transactions' => $outletTrans,
                    'avg_per_pax' => $outletPax > 0 ? round($outletRevenue / $outletPax, 2) : 0,
                    'prev_revenue' => round((float) $prevRevenue, 2),
                    'change_percent' => $change,
                    'trend'       => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
                ];
            }

            // Sort by revenue descending
            usort($outletsData, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        }

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

        // Week-specific data for weekly review
        $weekData = null;
        if ($weekStart) {
            $weekStartDate = Carbon::parse($weekStart);
            $weekEndDate = $weekStartDate->copy()->addDays(6);
            $prevWeekStart = $weekStartDate->copy()->subWeek();
            $prevWeekEnd = $prevWeekStart->copy()->addDays(6);

            // Filter daily sales to just this week
            $weekSales = array_filter($dailySales, function ($day) use ($weekStartDate, $weekEndDate) {
                $d = Carbon::parse($day['date']);
                return $d->gte($weekStartDate) && $d->lte($weekEndDate);
            });
            $weekSales = array_values($weekSales);

            // Calculate week totals
            $weekRevenue = array_sum(array_column($weekSales, 'revenue'));
            $weekPax = array_sum(array_column($weekSales, 'pax'));

            // Previous week data for comparison
            $prevWeekQuery = SalesRecord::whereBetween('sale_date', [$prevWeekStart, $prevWeekEnd]);
            if ($outletId) {
                $prevWeekQuery->where('outlet_id', $outletId);
            }
            $prevWeekRevenue = round((float) $prevWeekQuery->sum('total_revenue'), 2);
            $prevWeekPax = (int) $prevWeekQuery->sum('pax');

            $weekChange = $prevWeekRevenue > 0
                ? round(($weekRevenue - $prevWeekRevenue) / $prevWeekRevenue * 100, 1)
                : 0;

            // Per-outlet breakdown for this week (if multi-outlet)
            $weekOutletsData = [];
            if ($isMultiOutlet) {
                foreach ($outlets as $o) {
                    $oWeekRev = SalesRecord::where('outlet_id', $o->id)
                        ->whereBetween('sale_date', [$weekStartDate, $weekEndDate])
                        ->sum('total_revenue');
                    $oWeekPax = SalesRecord::where('outlet_id', $o->id)
                        ->whereBetween('sale_date', [$weekStartDate, $weekEndDate])
                        ->sum('pax');
                    $oPrevWeekRev = SalesRecord::where('outlet_id', $o->id)
                        ->whereBetween('sale_date', [$prevWeekStart, $prevWeekEnd])
                        ->sum('total_revenue');

                    $oChange = $oPrevWeekRev > 0
                        ? round(($oWeekRev - $oPrevWeekRev) / $oPrevWeekRev * 100, 1)
                        : 0;

                    $weekOutletsData[] = [
                        'outlet_name' => $o->name,
                        'revenue'     => round((float) $oWeekRev, 2),
                        'pax'         => (int) $oWeekPax,
                        'prev_revenue' => round((float) $oPrevWeekRev, 2),
                        'change_percent' => $oChange,
                        'trend'       => $oChange > 0 ? 'up' : ($oChange < 0 ? 'down' : 'flat'),
                    ];
                }
                usort($weekOutletsData, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
            }

            $weekData = [
                'week_start'       => $weekStartDate->format('Y-m-d'),
                'week_end'         => $weekEndDate->format('Y-m-d'),
                'week_label'       => $weekStartDate->format('M j') . ' - ' . $weekEndDate->format('M j, Y'),
                'prev_week_start'  => $prevWeekStart->format('Y-m-d'),
                'prev_week_end'    => $prevWeekEnd->format('Y-m-d'),
                'prev_week_label'  => $prevWeekStart->format('M j') . ' - ' . $prevWeekEnd->format('M j, Y'),
                'daily_sales'      => $weekSales,
                'revenue'          => round($weekRevenue, 2),
                'pax'              => $weekPax,
                'avg_per_pax'      => $weekPax > 0 ? round($weekRevenue / $weekPax, 2) : 0,
                'prev_revenue'     => $prevWeekRevenue,
                'prev_pax'         => $prevWeekPax,
                'change_percent'   => $weekChange,
                'outlets_data'     => $weekOutletsData,
            ];
        }

        // Labour costs for this month (FOH and BOH)
        $labourCostsQuery = LabourCost::with('allowances')
            ->whereMonth('month', $date->month)
            ->whereYear('month', $date->year);
        if ($outletId) {
            $labourCostsQuery->where('outlet_id', $outletId);
        }
        $labourCosts = $labourCostsQuery->get();

        $labourData = [
            'foh' => ['basic_salary' => 0, 'service_point' => 0, 'overtime' => 0, 'epf' => 0, 'eis' => 0, 'socso' => 0, 'allowances' => 0, 'total' => 0],
            'boh' => ['basic_salary' => 0, 'service_point' => 0, 'overtime' => 0, 'epf' => 0, 'eis' => 0, 'socso' => 0, 'allowances' => 0, 'total' => 0],
        ];

        foreach ($labourCosts as $lc) {
            $dept = $lc->department_type;
            if (isset($labourData[$dept])) {
                $labourData[$dept]['basic_salary'] += (float) $lc->basic_salary;
                $labourData[$dept]['service_point'] += (float) $lc->service_point;
                $labourData[$dept]['overtime'] += (float) $lc->overtime;
                $labourData[$dept]['epf'] += (float) $lc->epf;
                $labourData[$dept]['eis'] += (float) $lc->eis;
                $labourData[$dept]['socso'] += (float) $lc->socso;
                $labourData[$dept]['allowances'] += $lc->total_allowances;
                $labourData[$dept]['total'] += $lc->total_cost;
            }
        }

        $totalLabourCost = $labourData['foh']['total'] + $labourData['boh']['total'];
        $totalRevenue = $costSummary['totals']['revenue'] ?? 0;
        $labourCostPct = $totalRevenue > 0 ? round($totalLabourCost / $totalRevenue * 100, 1) : 0;

        // Previous month labour costs for comparison
        $prevLabourQuery = LabourCost::whereMonth('month', $date->copy()->subMonth()->month)
            ->whereYear('month', $date->copy()->subMonth()->year);
        if ($outletId) {
            $prevLabourQuery->where('outlet_id', $outletId);
        }
        $prevTotalLabour = round((float) $prevLabourQuery->get()->sum(fn ($lc) => $lc->total_cost), 2);
        $labourChange = $prevTotalLabour > 0 ? round(($totalLabourCost - $prevTotalLabour) / $prevTotalLabour * 100, 1) : 0;

        // Overtime claims for this month
        $otClaimsQuery = OvertimeClaim::whereBetween('claim_date', [$startOfMonth, $endOfMonth]);
        if ($outletId) {
            $otClaimsQuery->where('outlet_id', $outletId);
        }
        $otClaims = $otClaimsQuery->get();

        $overtimeData = [
            'total_hours'     => round((float) $otClaims->sum('total_ot_hours'), 1),
            'total_claims'    => $otClaims->count(),
            'approved_claims' => $otClaims->where('status', 'approved')->count(),
            'pending_claims'  => $otClaims->whereIn('status', ['draft', 'submitted'])->count(),
            'by_type' => [
                'normal_day'     => round((float) $otClaims->where('ot_type', 'normal_day')->sum('total_ot_hours'), 1),
                'public_holiday' => round((float) $otClaims->where('ot_type', 'public_holiday')->sum('total_ot_hours'), 1),
                'rest_day'       => round((float) $otClaims->where('ot_type', 'rest_day')->sum('total_ot_hours'), 1),
            ],
        ];

        // Previous month OT for comparison
        $prevOtQuery = OvertimeClaim::whereBetween('claim_date', [$date->copy()->subMonth()->startOfMonth(), $date->copy()->subMonth()->endOfMonth()]);
        if ($outletId) {
            $prevOtQuery->where('outlet_id', $outletId);
        }
        $prevOtHours = round((float) $prevOtQuery->sum('total_ot_hours'), 1);
        $overtimeData['prev_hours'] = $prevOtHours;
        $overtimeData['change_percent'] = $prevOtHours > 0
            ? round(($overtimeData['total_hours'] - $prevOtHours) / $prevOtHours * 100, 1)
            : 0;

        // Ingredient price changes this month
        $priceChanges = IngredientPriceHistory::with('ingredient')
            ->whereBetween('effective_date', [$startOfMonth, $endOfMonth])
            ->orderBy('effective_date', 'desc')
            ->get();

        $priceChangeData = [
            'total_changes' => $priceChanges->count(),
            'notable_changes' => [],
        ];

        // Get significant price changes (> 5% change)
        $ingredientGroups = $priceChanges->groupBy('ingredient_id');
        foreach ($ingredientGroups as $ingredientId => $changes) {
            if ($changes->count() < 2) continue;

            $latest = $changes->first();
            $previous = IngredientPriceHistory::where('ingredient_id', $ingredientId)
                ->where('effective_date', '<', $startOfMonth)
                ->orderBy('effective_date', 'desc')
                ->first();

            if ($previous && $previous->cost > 0) {
                $changePct = round(($latest->cost - $previous->cost) / $previous->cost * 100, 1);
                if (abs($changePct) >= 5) {
                    $priceChangeData['notable_changes'][] = [
                        'ingredient' => $latest->ingredient->name ?? 'Unknown',
                        'old_price'  => round((float) $previous->cost, 2),
                        'new_price'  => round((float) $latest->cost, 2),
                        'change_pct' => $changePct,
                        'trend'      => $changePct > 0 ? 'up' : 'down',
                    ];
                }
            }
        }

        // Sort by absolute change magnitude
        usort($priceChangeData['notable_changes'], fn ($a, $b) => abs($b['change_pct']) <=> abs($a['change_pct']));
        $priceChangeData['notable_changes'] = array_slice($priceChangeData['notable_changes'], 0, 10); // Top 10

        return [
            'company_name'       => $company->name ?? 'Unknown',
            'outlet_name'        => $outlet?->name ?? 'All Outlets',
            'is_multi_outlet'    => $isMultiOutlet,
            'outlets_data'       => $outletsData,
            'period'             => $period,
            'period_label'       => $date->format('F Y'),
            'prev_period_label'  => $prevPeriodLabel,
            'cost_summary'       => $costSummary,
            'prev_summary'       => $prevSummary,
            'daily_sales'        => $dailySales,
            'week_data'          => $weekData,
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
            'labour_costs'       => [
                'foh'           => $labourData['foh'],
                'boh'           => $labourData['boh'],
                'total'         => round($totalLabourCost, 2),
                'labour_pct'    => $labourCostPct,
                'prev_total'    => $prevTotalLabour,
                'change_percent' => $labourChange,
            ],
            'overtime'           => $overtimeData,
            'price_changes'      => $priceChangeData,
        ];
    }

    private function systemPrompt(array $context): string
    {
        $isMultiOutlet = !empty($context['is_multi_outlet']);
        $outletInstruction = $isMultiOutlet
            ? "You are analyzing ALL outlets combined. Include per-outlet comparisons and identify best/worst performers. "
            : "";

        return "You are an expert Food & Beverage operations analyst for \"{$context['company_name']}\" — {$context['outlet_name']}. "
            . $outletInstruction
            . "Analyze operational data and provide actionable insights to help maximize revenue and profit. "
            . "Be specific with numbers, percentages, and comparisons. Identify patterns and correlations with calendar events. "
            . "Format your response as JSON with the following structure:\n"
            . "{\n"
            . "  \"headline\": \"Brief one-line summary of overall performance (max 100 chars)\",\n"
            . "  \"performance_score\": \"excellent|good|average|below_average|poor\",\n"
            . "  \"key_metrics\": [\n"
            . "    {\"label\": \"Metric name\", \"value\": \"RM X,XXX or X%\", \"trend\": \"up|down|flat\", \"note\": \"Brief context\"}\n"
            . "  ],\n"
            . ($isMultiOutlet
                ? "  \"outlets_summary\": [\n"
                . "    {\"name\": \"Outlet name\", \"revenue\": \"RM X,XXX\", \"trend\": \"up|down|flat\", \"note\": \"Brief performance note\"}\n"
                . "  ],\n"
                : "")
            . "  \"highlights\": [\"Positive insight 1\", \"Positive insight 2\"],\n"
            . "  \"concerns\": [\"Area needing attention 1\", \"Area needing attention 2\"],\n"
            . "  \"recommendations\": [\n"
            . "    {\"title\": \"Action title\", \"description\": \"What to do and expected impact\", \"priority\": \"high|medium|low\"}\n"
            . "  ],\n"
            . "  \"detailed_analysis\": \"Markdown-formatted detailed analysis with ## headers for sections"
            . ($isMultiOutlet ? ". Include a section for each outlet's performance." : "") . "\"\n"
            . "}\n"
            . "Keep insights brief and actionable. Use Malaysian Ringgit (RM) for currency.";
    }

    public function buildPrompt(array $context, string $analysisType, ?string $customQuestion): string
    {
        $sections = [];
        $prevPeriodLabel = $context['prev_period_label'] ?? 'Previous Month';

        // Revenue summary
        $totals = $context['cost_summary']['totals'];
        $prevTotals = $context['prev_summary']['totals'];

        // For weekly review, show week-specific data first
        if ($analysisType === 'weekly_review' && !empty($context['week_data'])) {
            $week = $context['week_data'];
            $sections[] = "## Weekly Review: {$week['week_label']}";
            $sections[] = "**Analyzing:** {$context['outlet_name']}\n";

            // Week summary
            $sections[] = "## This Week's Performance";
            $weekTrend = $week['change_percent'] >= 0 ? '↑' : '↓';
            $weekChangeStr = ($week['change_percent'] >= 0 ? '+' : '') . $week['change_percent'] . '%';
            $sections[] = "| Metric | This Week | Previous Week ({$week['prev_week_label']}) | Change |";
            $sections[] = "|--------|-----------|----------------------------------------|--------|";
            $sections[] = "| Revenue | RM " . number_format($week['revenue'], 2) . " | RM " . number_format($week['prev_revenue'], 2) . " | {$weekTrend} {$weekChangeStr} |";
            $sections[] = "| Pax | " . number_format($week['pax']) . " | " . number_format($week['prev_pax']) . " | |";
            $sections[] = "| Avg/Pax | RM " . number_format($week['avg_per_pax'], 2) . " | | |";
            $sections[] = "";

            // Per-outlet breakdown for this week (if multi-outlet)
            if (!empty($context['is_multi_outlet']) && !empty($week['outlets_data'])) {
                $sections[] = "## Performance by Outlet (This Week)";
                $sections[] = "| Outlet | Revenue | Pax | vs Previous Week |";
                $sections[] = "|--------|---------|-----|------------------|";
                foreach ($week['outlets_data'] as $outlet) {
                    $trendIcon = $outlet['trend'] === 'up' ? '↑' : ($outlet['trend'] === 'down' ? '↓' : '→');
                    $changeStr = ($outlet['change_percent'] >= 0 ? '+' : '') . $outlet['change_percent'] . '%';
                    $sections[] = "| {$outlet['outlet_name']} | RM " . number_format($outlet['revenue'], 2)
                        . " | " . number_format($outlet['pax'])
                        . " | {$trendIcon} {$changeStr} |";
                }
                $sections[] = "";
            }

            // Daily breakdown for this week only
            if (!empty($week['daily_sales'])) {
                $sections[] = "## Daily Sales (This Week)";
                $sections[] = "| Date | Day | Revenue | Pax | Avg/Pax |";
                $sections[] = "|------|-----|---------|-----|---------|";
                foreach ($week['daily_sales'] as $day) {
                    $sections[] = "| {$day['date']} | {$day['day']} | RM " . number_format($day['revenue'], 2) . " | {$day['pax']} | RM " . number_format($day['avg'], 2) . " |";
                }
                $sections[] = "";
            }

            // Brief monthly context
            $sections[] = "## Monthly Context ({$context['period_label']})";
            $sections[] = "- Month-to-date Revenue: RM " . number_format($totals['revenue'], 2);
            $sections[] = "- Month-to-date Cost %: {$totals['cost_pct']}%";
            $sections[] = "";

        } else {
            // Monthly view - existing logic with explicit period labels
            $sections[] = "## Period: {$context['period_label']}\n";

            // Per-outlet breakdown when viewing all outlets
            if (!empty($context['is_multi_outlet']) && !empty($context['outlets_data'])) {
                $sections[] = "## Performance by Outlet (vs {$prevPeriodLabel})";
                $sections[] = "| Outlet | Revenue | Pax | Avg/Pax | vs {$prevPeriodLabel} |";
                $sections[] = "|--------|---------|-----|---------|" . str_repeat('-', strlen($prevPeriodLabel) + 4) . "|";
                foreach ($context['outlets_data'] as $outlet) {
                    $trendIcon = $outlet['trend'] === 'up' ? '↑' : ($outlet['trend'] === 'down' ? '↓' : '→');
                    $changeStr = ($outlet['change_percent'] >= 0 ? '+' : '') . $outlet['change_percent'] . '%';
                    $sections[] = "| {$outlet['outlet_name']} | RM " . number_format($outlet['revenue'], 2)
                        . " | " . number_format($outlet['pax'])
                        . " | RM " . number_format($outlet['avg_per_pax'], 2)
                        . " | {$trendIcon} {$changeStr} |";
                }
                $sections[] = "";

                // Calculate totals for all outlets
                $totalRevenue = array_sum(array_column($context['outlets_data'], 'revenue'));
                $totalPax = array_sum(array_column($context['outlets_data'], 'pax'));
                $sections[] = "**Combined Total:** RM " . number_format($totalRevenue, 2) . " | " . number_format($totalPax) . " pax";
                $sections[] = "";
            }

            // P&L overview with explicit period labels
            $sections[] = "## Profit & Loss Summary";
            $sections[] = "| Metric | {$context['period_label']} | {$prevPeriodLabel} | Change |";
            $sections[] = "|--------|" . str_repeat('-', strlen($context['period_label']) + 2) . "|" . str_repeat('-', strlen($prevPeriodLabel) + 2) . "|--------|";

            $revChange = $prevTotals['revenue'] > 0 ? round(($totals['revenue'] - $prevTotals['revenue']) / $prevTotals['revenue'] * 100, 1) : 0;
            $sections[] = "| Revenue | RM " . number_format($totals['revenue'], 2) . " | RM " . number_format($prevTotals['revenue'], 2) . " | {$revChange}% |";

            $cogsChange = $prevTotals['cogs'] > 0 ? round(($totals['cogs'] - $prevTotals['cogs']) / $prevTotals['cogs'] * 100, 1) : 0;
            $sections[] = "| COGS | RM " . number_format($totals['cogs'], 2) . " | RM " . number_format($prevTotals['cogs'], 2) . " | {$cogsChange}% |";
            $sections[] = "| Cost % | {$totals['cost_pct']}% | {$prevTotals['cost_pct']}% | " . round($totals['cost_pct'] - $prevTotals['cost_pct'], 1) . "pp |";
            $sections[] = "| Purchases | RM " . number_format($totals['purchases'], 2) . " | RM " . number_format($prevTotals['purchases'], 2) . " | |";
            $sections[] = "| Wastage | RM " . number_format($context['total_wastage'], 2) . " | | |";
            $sections[] = "| Staff Meals | RM " . number_format($context['staff_meals_total'], 2) . " | | |";
            $sections[] = "";
        }

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

        // Labour Costs (FOH & BOH)
        if (!empty($context['labour_costs']) && $context['labour_costs']['total'] > 0) {
            $labour = $context['labour_costs'];
            $sections[] = "## Labour Costs (vs {$prevPeriodLabel})";
            $sections[] = "| Department | Basic Salary | Service Point | Overtime | EPF | SOCSO | EIS | Allowances | Total |";
            $sections[] = "|------------|-------------|---------------|----------|-----|-------|-----|------------|-------|";

            foreach (['foh' => 'Front of House', 'boh' => 'Back of House'] as $key => $label) {
                $dept = $labour[$key];
                if ($dept['total'] > 0) {
                    $sections[] = "| {$label} | RM " . number_format($dept['basic_salary'], 2)
                        . " | RM " . number_format($dept['service_point'], 2)
                        . " | RM " . number_format($dept['overtime'], 2)
                        . " | RM " . number_format($dept['epf'], 2)
                        . " | RM " . number_format($dept['socso'], 2)
                        . " | RM " . number_format($dept['eis'], 2)
                        . " | RM " . number_format($dept['allowances'], 2)
                        . " | RM " . number_format($dept['total'], 2) . " |";
                }
            }

            $labourTrend = $labour['change_percent'] >= 0 ? '↑' : '↓';
            $labourChangeStr = ($labour['change_percent'] >= 0 ? '+' : '') . $labour['change_percent'] . '%';
            $sections[] = "";
            $sections[] = "**Total Labour Cost:** RM " . number_format($labour['total'], 2)
                . " ({$labour['labour_pct']}% of revenue) | {$labourTrend} {$labourChangeStr} vs {$prevPeriodLabel}";
            $sections[] = "";
        }

        // Overtime Claims
        if (!empty($context['overtime']) && $context['overtime']['total_claims'] > 0) {
            $ot = $context['overtime'];
            $sections[] = "## Overtime Claims (vs {$prevPeriodLabel})";
            $sections[] = "| Metric | Value |";
            $sections[] = "|--------|-------|";
            $sections[] = "| Total OT Hours | " . number_format($ot['total_hours'], 1) . " hrs |";
            $sections[] = "| Total Claims | {$ot['total_claims']} |";
            $sections[] = "| Approved | {$ot['approved_claims']} |";
            $sections[] = "| Pending | {$ot['pending_claims']} |";
            $sections[] = "| Normal Day OT | " . number_format($ot['by_type']['normal_day'], 1) . " hrs |";
            $sections[] = "| Public Holiday OT | " . number_format($ot['by_type']['public_holiday'], 1) . " hrs |";
            $sections[] = "| Rest Day OT | " . number_format($ot['by_type']['rest_day'], 1) . " hrs |";

            $otTrend = $ot['change_percent'] >= 0 ? '↑' : '↓';
            $otChangeStr = ($ot['change_percent'] >= 0 ? '+' : '') . $ot['change_percent'] . '%';
            $sections[] = "";
            $sections[] = "**Prev Month:** {$ot['prev_hours']} hrs | **Change:** {$otTrend} {$otChangeStr} vs {$prevPeriodLabel}";
            $sections[] = "";
        }

        // Ingredient Price Changes
        if (!empty($context['price_changes']) && $context['price_changes']['total_changes'] > 0) {
            $prices = $context['price_changes'];
            $sections[] = "## Ingredient Price Changes This Month";
            $sections[] = "Total price updates recorded: **{$prices['total_changes']}**";
            $sections[] = "";

            if (!empty($prices['notable_changes'])) {
                $sections[] = "### Significant Price Changes (≥5%)";
                $sections[] = "| Ingredient | Old Price | New Price | Change |";
                $sections[] = "|------------|-----------|-----------|--------|";
                foreach ($prices['notable_changes'] as $change) {
                    $trendIcon = $change['trend'] === 'up' ? '↑' : '↓';
                    $changeStr = ($change['change_pct'] >= 0 ? '+' : '') . $change['change_pct'] . '%';
                    $sections[] = "| {$change['ingredient']} | RM " . number_format($change['old_price'], 2)
                        . " | RM " . number_format($change['new_price'], 2)
                        . " | {$trendIcon} {$changeStr} |";
                }
                $sections[] = "";
            }
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
                    . "3. **Cost Analysis** — COGS % by category, areas of concern, month-over-month changes\n"
                    . "4. **Labour Cost Analysis** — total labour cost as % of revenue, FOH vs BOH breakdown, is it within acceptable range (typically 25-35%)?\n"
                    . "5. **Overtime Assessment** — total OT hours and cost, compare to previous month, is overtime trending up or down? Any concerns?\n"
                    . "6. **Inventory Health** — stock movement, are closing values reasonable? Any overstocking or understocking signals?\n"
                    . "7. **Wastage & Staff Meals** — breakdown by category/item, are these within acceptable range? Top offenders?\n"
                    . "8. **Ingredient Price Impact** — highlight any significant ingredient price increases (≥5%) and their impact on margins\n"
                    . "9. **Event Correlation** — how calendar events impacted sales (correlate event dates with daily revenue)\n"
                    . "10. **Historical Comparison** — compare this month to previous months' trend, is performance improving or declining?\n"
                    . "11. **Key Recommendations** — 3-5 specific, actionable steps to improve next month, including any labour or cost optimizations";
                break;

            case 'weekly_review':
                $weekLabel = $context['week_data']['week_label'] ?? 'Selected Week';
                $prevWeekLabel = $context['week_data']['prev_week_label'] ?? 'Previous Week';
                $isMultiOutlet = !empty($context['is_multi_outlet']);

                $sections[] = "Provide a focused weekly operations review for **{$weekLabel}**. "
                    . "All comparisons should be vs the previous week (**{$prevWeekLabel}**).\n\n"
                    . "1. **Weekly Summary** — state the total revenue, pax, and average check for this week, with % change vs previous week ({$prevWeekLabel})\n"
                    . ($isMultiOutlet
                        ? "2. **Outlet Performance** — rank outlets by revenue, highlight best/worst performers vs previous week\n"
                        : "")
                    . (($isMultiOutlet ? "3" : "2") . ". **Day-by-Day Analysis** — identify the best and worst performing days, explain any patterns\n")
                    . (($isMultiOutlet ? "4" : "3") . ". **Week-over-Week Trend** — is this week up or down vs previous week? What's driving the change?\n")
                    . (($isMultiOutlet ? "5" : "4") . ". **Immediate Actions** — 2-3 specific quick wins for the upcoming week\n\n")
                    . "IMPORTANT: When showing trends like 'Down X%', always specify the comparison period explicitly "
                    . "(e.g., 'Down 15% vs {$prevWeekLabel}').";
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
                $nextMonth = Carbon::createFromFormat('!Y-m', $context['period'])->addMonth();
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
