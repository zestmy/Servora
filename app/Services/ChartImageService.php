<?php

namespace App\Services;

class ChartImageService
{
    protected string $baseUrl = 'https://quickchart.io/chart';

    /**
     * Generate a line chart URL for daily revenue trend.
     */
    public function dailyRevenueChart(array $dailyData, int $width = 600, int $height = 300): string
    {
        $labels = array_map(fn($d) => $d['day_name'] ?? $d['date'], $dailyData);
        $values = array_map(fn($d) => $d['revenue'], $dailyData);

        $config = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Revenue (RM)',
                    'data' => $values,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ]],
            ],
            'options' => [
                'plugins' => [
                    'legend' => ['display' => false],
                    'title' => [
                        'display' => true,
                        'text' => 'Daily Revenue',
                        'font' => ['size' => 16],
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'ticks' => ['callback' => 'function(v) { return "RM " + v.toLocaleString(); }'],
                    ],
                ],
            ],
        ];

        return $this->buildUrl($config, $width, $height);
    }

    /**
     * Generate a bar chart for meal period breakdown.
     */
    public function mealPeriodChart(array $mealPeriodData, int $width = 500, int $height = 300): string
    {
        $labels = array_map(fn($d) => $d['label'], $mealPeriodData);
        $values = array_map(fn($d) => $d['revenue'], $mealPeriodData);

        $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'];

        $config = [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Revenue (RM)',
                    'data' => $values,
                    'backgroundColor' => array_slice($colors, 0, count($values)),
                ]],
            ],
            'options' => [
                'plugins' => [
                    'legend' => ['display' => false],
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue by Meal Period',
                        'font' => ['size' => 16],
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                    ],
                ],
            ],
        ];

        return $this->buildUrl($config, $width, $height);
    }

    /**
     * Generate a doughnut chart for meal period distribution.
     */
    public function mealPeriodPieChart(array $mealPeriodData, int $width = 400, int $height = 300): string
    {
        $labels = array_map(fn($d) => $d['label'], $mealPeriodData);
        $values = array_map(fn($d) => $d['revenue'], $mealPeriodData);

        $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899'];

        $config = [
            'type' => 'doughnut',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'data' => $values,
                    'backgroundColor' => array_slice($colors, 0, count($values)),
                ]],
            ],
            'options' => [
                'plugins' => [
                    'legend' => [
                        'position' => 'right',
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Meal Period Distribution',
                        'font' => ['size' => 16],
                    ],
                    'datalabels' => [
                        'display' => true,
                        'formatter' => 'function(v, ctx) { return ctx.chart.data.labels[ctx.dataIndex] + ": " + Math.round(v/ctx.chart.data.datasets[0].data.reduce((a,b)=>a+b)*100) + "%"; }',
                        'color' => '#fff',
                        'font' => ['weight' => 'bold'],
                    ],
                ],
            ],
        ];

        return $this->buildUrl($config, $width, $height);
    }

    /**
     * Generate a pie chart for sales by category.
     */
    public function salesByCategoryChart(array $categories, int $width = 500, int $height = 350): string
    {
        $labels = array_map(fn($d) => mb_substr($d['name'], 0, 20), $categories);
        $values = array_map(fn($d) => $d['revenue'], $categories);
        $colors = array_map(fn($d) => $d['color'] ?? '#6366f1', $categories);

        $config = [
            'type' => 'pie',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'data' => $values,
                    'backgroundColor' => $colors,
                ]],
            ],
            'options' => [
                'plugins' => [
                    'legend' => [
                        'position' => 'right',
                        'labels' => ['boxWidth' => 12, 'padding' => 8],
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Sales by Category',
                        'font' => ['size' => 16],
                    ],
                    'datalabels' => [
                        'display' => true,
                        'formatter' => 'function(value, ctx) { var sum = ctx.dataset.data.reduce((a, b) => a + b, 0); var pct = (value * 100 / sum).toFixed(1); return pct + "%"; }',
                        'color' => '#fff',
                        'font' => ['weight' => 'bold'],
                    ],
                ],
            ],
        ];

        return $this->buildUrl($config, $width, $height);
    }

    /**
     * Generate a comparison gauge/progress chart.
     */
    public function comparisonGauge(float $current, float $previous, string $label = 'vs Previous', int $width = 300, int $height = 200): string
    {
        $change = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0;
        $isPositive = $change >= 0;

        $config = [
            'type' => 'radialGauge',
            'data' => [
                'datasets' => [[
                    'data' => [abs($change)],
                    'backgroundColor' => $isPositive ? '#10B981' : '#EF4444',
                ]],
            ],
            'options' => [
                'centerPercentage' => 80,
                'rotation' => -Math.PI,
                'circumference' => Math.PI,
                'plugins' => [
                    'datalabels' => [
                        'display' => true,
                        'formatter' => ($isPositive ? '+' : '-') . abs($change) . '%',
                        'font' => ['size' => 24, 'weight' => 'bold'],
                        'color' => $isPositive ? '#10B981' : '#EF4444',
                    ],
                ],
                'title' => [
                    'display' => true,
                    'text' => $label,
                ],
            ],
        ];

        return $this->buildUrl($config, $width, $height);
    }

    /**
     * Generate a weekly comparison bar chart.
     */
    public function weeklyComparisonChart(array $weeklyData, int $width = 600, int $height = 300): string
    {
        $labels = array_map(fn($d) => $d['week_start'], $weeklyData);
        $values = array_map(fn($d) => $d['revenue'], $weeklyData);

        $config = [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Weekly Revenue (RM)',
                    'data' => $values,
                    'backgroundColor' => '#3B82F6',
                ]],
            ],
            'options' => [
                'plugins' => [
                    'legend' => ['display' => false],
                    'title' => [
                        'display' => true,
                        'text' => 'Weekly Revenue Trend',
                        'font' => ['size' => 16],
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                    ],
                ],
            ],
        ];

        return $this->buildUrl($config, $width, $height);
    }

    /**
     * Generate KPI card as an image.
     */
    public function kpiCard(string $label, string $value, ?string $trend = null, int $width = 200, int $height = 120): string
    {
        $trendColor = '#6B7280';
        $trendIcon = '';

        if ($trend) {
            if (str_starts_with($trend, '+')) {
                $trendColor = '#10B981';
                $trendIcon = '↑ ';
            } elseif (str_starts_with($trend, '-')) {
                $trendColor = '#EF4444';
                $trendIcon = '↓ ';
            }
        }

        $config = [
            'type' => 'bar',
            'data' => [
                'labels' => [''],
                'datasets' => [[
                    'data' => [0],
                ]],
            ],
            'options' => [
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => $label,
                        'font' => ['size' => 12],
                        'color' => '#6B7280',
                    ],
                    'subtitle' => [
                        'display' => true,
                        'text' => $value,
                        'font' => ['size' => 24, 'weight' => 'bold'],
                        'color' => '#1F2937',
                        'padding' => ['top' => 10],
                    ],
                    'annotation' => $trend ? [
                        'annotations' => [
                            [
                                'type' => 'label',
                                'content' => $trendIcon . $trend,
                                'color' => $trendColor,
                                'font' => ['size' => 14],
                            ],
                        ],
                    ] : [],
                ],
            ],
        ];

        return $this->buildUrl($config, $width, $height);
    }

    /**
     * Build the QuickChart URL.
     */
    protected function buildUrl(array $config, int $width, int $height): string
    {
        $chartJson = json_encode($config);

        $params = [
            'c' => $chartJson,
            'w' => $width,
            'h' => $height,
            'bkg' => 'white',
            'f' => 'png',
        ];

        return $this->baseUrl . '?' . http_build_query($params);
    }

    /**
     * Generate a short URL for a chart (useful for long configs).
     */
    public function getShortUrl(array $config, int $width = 600, int $height = 300): ?string
    {
        try {
            $response = \Illuminate\Support\Facades\Http::post('https://quickchart.io/chart/create', [
                'chart' => $config,
                'width' => $width,
                'height' => $height,
                'backgroundColor' => 'white',
                'format' => 'png',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['url'] ?? null;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('QuickChart short URL creation failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
