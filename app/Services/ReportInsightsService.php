<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReportInsightsService
{
    /**
     * Generate AI insights for a daily sales report.
     */
    public function generateDailyInsights(array $salesData): array
    {
        // Skip AI insights if no sales data
        $today = $salesData['today'] ?? [];
        if (empty($today['revenue']) || $today['revenue'] <= 0) {
            return $this->noDataResponse('No sales recorded for this day');
        }

        $prompt = $this->buildDailyPrompt($salesData);
        return $this->callAI($prompt, 'daily_sales');
    }

    /**
     * Generate AI insights for a weekly performance report.
     */
    public function generateWeeklyInsights(array $salesData): array
    {
        // Skip AI insights if no sales data
        $thisWeek = $salesData['this_week'] ?? [];
        if (empty($thisWeek['revenue']) || $thisWeek['revenue'] <= 0) {
            return $this->noDataResponse('No sales recorded for this week');
        }

        $prompt = $this->buildWeeklyPrompt($salesData);
        return $this->callAI($prompt, 'weekly_performance');
    }

    /**
     * Generate AI insights for a monthly summary report.
     */
    public function generateMonthlyInsights(array $salesData): array
    {
        // Skip AI insights if no sales data
        $thisMonth = $salesData['this_month'] ?? [];
        if (empty($thisMonth['revenue']) || $thisMonth['revenue'] <= 0) {
            return $this->noDataResponse('No sales recorded for this month');
        }

        $prompt = $this->buildMonthlyPrompt($salesData);
        return $this->callAI($prompt, 'monthly_summary');
    }

    /**
     * Return a no-data response when there's no sales to analyze.
     */
    protected function noDataResponse(string $reason): array
    {
        return [
            'success' => true,
            'insights' => null,
            'skipped' => true,
            'reason' => $reason,
        ];
    }

    protected function callAI(string $prompt, string $reportType): array
    {
        $apiKey = AppSetting::get('openrouter_api_key');

        if (empty($apiKey)) {
            Log::warning('OpenRouter API key not configured for report insights');
            return [
                'success' => false,
                'insights' => null,
                'error' => 'AI API key not configured',
            ];
        }

        $model = AppSetting::get('openrouter_model') ?: 'anthropic/claude-sonnet-4';

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('app.url', 'http://localhost'),
                    'X-Title' => config('app.name', 'Servora'),
                ])
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => $model,
                    'max_tokens' => 2048,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('OpenRouter API error for report insights', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'report_type' => $reportType,
                ]);
                return [
                    'success' => false,
                    'insights' => null,
                    'error' => 'AI API request failed',
                ];
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';

            return [
                'success' => true,
                'insights' => $this->parseInsights($content),
                'raw' => $content,
                'model' => $data['model'] ?? $model,
                'tokens' => [
                    'input' => $data['usage']['prompt_tokens'] ?? null,
                    'output' => $data['usage']['completion_tokens'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Report insights generation failed', [
                'error' => $e->getMessage(),
                'report_type' => $reportType,
            ]);
            return [
                'success' => false,
                'insights' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function systemPrompt(): string
    {
        return "You are a Food & Beverage business analyst assistant. Generate concise, actionable insights for sales reports. "
            . "Focus on key metrics, comparisons, and recommendations. "
            . "Format your response as JSON with the following structure:\n"
            . "{\n"
            . "  \"headline\": \"Brief one-line summary of performance\",\n"
            . "  \"key_metrics\": [\"metric1\", \"metric2\", \"metric3\"],\n"
            . "  \"highlights\": [\"positive insight 1\", \"positive insight 2\"],\n"
            . "  \"concerns\": [\"area needing attention\"],\n"
            . "  \"recommendations\": [\"action item 1\", \"action item 2\"],\n"
            . "  \"comparison_summary\": \"Brief comparison with previous period\"\n"
            . "}\n"
            . "Keep each insight brief (under 100 characters). Be specific with numbers and percentages.";
    }

    protected function buildDailyPrompt(array $data): string
    {
        $today = $data['today'] ?? [];
        $comparisons = $data['comparisons'] ?? [];
        $byMealPeriod = $data['by_meal_period'] ?? [];
        $topItems = $data['top_items'] ?? [];

        $lines = [
            "## Daily Sales Report for {$data['date']}",
            "",
            "### Today's Performance",
            "- Revenue: RM " . number_format($today['revenue'] ?? 0, 2),
            "- Pax (Covers): " . ($today['pax'] ?? 0),
            "- Transactions: " . ($today['transactions'] ?? 0),
            "- Average per Pax: RM " . number_format($today['avg_per_pax'] ?? 0, 2),
            "- Average per Transaction: RM " . number_format($today['avg_per_transaction'] ?? 0, 2),
            "",
            "### Comparisons",
        ];

        if (!empty($comparisons['yesterday'])) {
            $lines[] = "- vs Yesterday: " . $this->formatComparison($comparisons['yesterday']);
        }
        if (!empty($comparisons['last_week'])) {
            $lines[] = "- vs Same Day Last Week: " . $this->formatComparison($comparisons['last_week']);
        }
        if (!empty($comparisons['last_year'])) {
            $lines[] = "- vs Same Day Last Year: " . $this->formatComparison($comparisons['last_year']);
        }

        if (!empty($byMealPeriod)) {
            $lines[] = "";
            $lines[] = "### By Meal Period";
            foreach ($byMealPeriod as $period) {
                $lines[] = "- {$period['label']}: RM " . number_format($period['revenue'], 2) . " ({$period['percentage']}%)";
            }
        }

        if (!empty($topItems)) {
            $lines[] = "";
            $lines[] = "### Top Selling Items";
            foreach ($topItems as $item) {
                $lines[] = "- {$item['name']}: {$item['quantity']} units, RM " . number_format($item['revenue'], 2);
            }
        }

        $lines[] = "";
        $lines[] = "Generate insights for this daily report in the JSON format specified.";

        return implode("\n", $lines);
    }

    protected function buildWeeklyPrompt(array $data): string
    {
        $thisWeek = $data['this_week'] ?? [];
        $comparisons = $data['comparisons'] ?? [];
        $dailyBreakdown = $data['daily_breakdown'] ?? [];
        $byMealPeriod = $data['by_meal_period'] ?? [];
        $topItems = $data['top_items'] ?? [];
        $bestDay = $data['best_day'] ?? null;
        $worstDay = $data['worst_day'] ?? null;

        $lines = [
            "## Weekly Performance Report",
            "### Period: {$data['period_start']} to {$data['period_end']}",
            "",
            "### Week Summary",
            "- Total Revenue: RM " . number_format($thisWeek['revenue'] ?? 0, 2),
            "- Total Pax: " . ($thisWeek['pax'] ?? 0),
            "- Total Transactions: " . ($thisWeek['transactions'] ?? 0),
            "- Average Daily Revenue: RM " . number_format($thisWeek['avg_daily_revenue'] ?? 0, 2),
            "- Average per Pax: RM " . number_format($thisWeek['avg_per_pax'] ?? 0, 2),
            "- Days with Sales: " . ($thisWeek['days_with_sales'] ?? 0),
            "",
            "### Comparisons",
        ];

        if (!empty($comparisons['last_week'])) {
            $lines[] = "- vs Last Week: " . $this->formatComparison($comparisons['last_week']);
        }
        if (!empty($comparisons['last_year'])) {
            $lines[] = "- vs Same Week Last Year: " . $this->formatComparison($comparisons['last_year']);
        }

        if ($bestDay) {
            $lines[] = "";
            $lines[] = "### Best Day: {$bestDay['day_name']} ({$bestDay['date']})";
            $lines[] = "- Revenue: RM " . number_format($bestDay['revenue'], 2);
        }

        if ($worstDay) {
            $lines[] = "";
            $lines[] = "### Lowest Day: {$worstDay['day_name']} ({$worstDay['date']})";
            $lines[] = "- Revenue: RM " . number_format($worstDay['revenue'], 2);
        }

        if (!empty($dailyBreakdown)) {
            $lines[] = "";
            $lines[] = "### Daily Breakdown";
            foreach ($dailyBreakdown as $day) {
                $lines[] = "- {$day['day_name']}: RM " . number_format($day['revenue'], 2) . " ({$day['pax']} pax)";
            }
        }

        if (!empty($byMealPeriod)) {
            $lines[] = "";
            $lines[] = "### By Meal Period";
            foreach ($byMealPeriod as $period) {
                $lines[] = "- {$period['label']}: RM " . number_format($period['revenue'], 2) . " ({$period['percentage']}%)";
            }
        }

        if (!empty($topItems)) {
            $lines[] = "";
            $lines[] = "### Top 10 Items";
            foreach (array_slice($topItems, 0, 10) as $item) {
                $lines[] = "- {$item['name']}: {$item['quantity']} units, RM " . number_format($item['revenue'], 2);
            }
        }

        $lines[] = "";
        $lines[] = "Generate insights for this weekly report in the JSON format specified.";

        return implode("\n", $lines);
    }

    protected function buildMonthlyPrompt(array $data): string
    {
        $thisMonth = $data['this_month'] ?? [];
        $comparisons = $data['comparisons'] ?? [];
        $weeklyBreakdown = $data['weekly_breakdown'] ?? [];
        $byMealPeriod = $data['by_meal_period'] ?? [];
        $topItems = $data['top_items'] ?? [];

        $lines = [
            "## Monthly Summary Report",
            "### Period: {$data['period_start']} to {$data['period_end']}",
            "",
            "### Month Summary",
            "- Total Revenue: RM " . number_format($thisMonth['revenue'] ?? 0, 2),
            "- Total Pax: " . ($thisMonth['pax'] ?? 0),
            "- Total Transactions: " . ($thisMonth['transactions'] ?? 0),
            "- Total Discounts: RM " . number_format($thisMonth['discounts'] ?? 0, 2),
            "- Average Daily Revenue: RM " . number_format($thisMonth['avg_daily_revenue'] ?? 0, 2),
            "- Average per Pax: RM " . number_format($thisMonth['avg_per_pax'] ?? 0, 2),
            "- Days with Sales: " . ($thisMonth['days_with_sales'] ?? 0),
            "",
            "### Comparisons",
        ];

        if (!empty($comparisons['last_month'])) {
            $lines[] = "- vs Last Month: " . $this->formatComparison($comparisons['last_month']);
        }
        if (!empty($comparisons['last_year'])) {
            $lines[] = "- vs Same Month Last Year: " . $this->formatComparison($comparisons['last_year']);
        }

        if (!empty($weeklyBreakdown)) {
            $lines[] = "";
            $lines[] = "### Weekly Breakdown";
            foreach ($weeklyBreakdown as $week) {
                $lines[] = "- Week of {$week['week_start']}: RM " . number_format($week['revenue'], 2) . " ({$week['pax']} pax)";
            }
        }

        if (!empty($byMealPeriod)) {
            $lines[] = "";
            $lines[] = "### By Meal Period";
            foreach ($byMealPeriod as $period) {
                $lines[] = "- {$period['label']}: RM " . number_format($period['revenue'], 2) . " ({$period['percentage']}%)";
            }
        }

        if (!empty($topItems)) {
            $lines[] = "";
            $lines[] = "### Top 15 Items";
            foreach (array_slice($topItems, 0, 15) as $item) {
                $lines[] = "- {$item['name']}: {$item['quantity']} units, RM " . number_format($item['revenue'], 2);
            }
        }

        $lines[] = "";
        $lines[] = "Generate insights for this monthly report in the JSON format specified.";

        return implode("\n", $lines);
    }

    protected function formatComparison(array $comparison): string
    {
        $trend = $comparison['trend'] ?? 'flat';
        $change = $comparison['change_percent'] ?? 0;
        $amount = $comparison['change_amount'] ?? 0;

        $arrow = match ($trend) {
            'up' => '↑',
            'down' => '↓',
            default => '→',
        };

        $sign = $amount >= 0 ? '+' : '';
        return "{$arrow} {$sign}{$change}% (RM " . number_format($amount, 2) . ")";
    }

    protected function parseInsights(string $content): ?array
    {
        // Try to extract JSON from the response
        $content = trim($content);

        // Remove markdown code blocks if present
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

        // If JSON parsing fails, return structured fallback from plain text
        return [
            'headline' => substr($content, 0, 100),
            'key_metrics' => [],
            'highlights' => [],
            'concerns' => [],
            'recommendations' => [],
            'comparison_summary' => '',
            'raw_text' => $content,
        ];
    }
}
