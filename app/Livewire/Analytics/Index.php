<?php

namespace App\Livewire\Analytics;

use App\Models\AiAnalysisLog;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Outlet;
use App\Services\AiAnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $period = '';
    public ?int $outletId = null;
    public string $analysisType = 'monthly_review';
    public string $customQuestion = '';
    public string $weekStart = '';

    public string $responseText = '';
    public ?array $insights = null;
    public ?array $context = null;
    public bool $cached = false;
    public ?array $tokens = null;
    public string $model = '';
    public string $respondedAt = '';
    public ?int $viewingLogId = null;

    // Multi-outlet results
    public bool $isMultiOutlet = false;
    public array $outletResults = [];
    public int $activeOutletTab = 0;

    public string $error = '';

    // Saved reports tab
    public string $activeTab = 'generate'; // generate | saved

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
        $this->weekStart = now()->startOfWeek()->format('Y-m-d');

        $activeOutletId = session('active_outlet_id');
        if ($activeOutletId) {
            $this->outletId = (int) $activeOutletId;
        }
    }

    public function previousMonth(): void
    {
        $this->period = Carbon::createFromFormat('!Y-m', $this->period)->subMonth()->format('Y-m');
        $this->resetResponse();
    }

    public function nextMonth(): void
    {
        $this->period = Carbon::createFromFormat('!Y-m', $this->period)->addMonth()->format('Y-m');
        $this->resetResponse();
    }

    public function updatedOutletId(): void
    {
        $this->resetResponse();
    }

    public function runAnalysis(): void
    {
        $this->error = '';
        $this->responseText = '';
        $this->insights = null;
        $this->context = null;
        $this->viewingLogId = null;
        $this->isMultiOutlet = false;
        $this->outletResults = [];
        $this->activeOutletTab = 0;

        try {
            $service = app(AiAnalyticsService::class);
            $customQ = null;
            if ($this->analysisType === 'custom') {
                $customQ = $this->customQuestion;
            } elseif ($this->analysisType === 'weekly_review') {
                $customQ = $this->weekStart;
            }

            $result = $service->analyze(
                $this->period,
                $this->outletId ?: null,
                $this->analysisType,
                $customQ
            );

            // Check if multi-outlet response
            if (!empty($result['is_multi_outlet'])) {
                $this->isMultiOutlet = true;
                $this->outletResults = $result['outlets'] ?? [];
                $this->cached = $result['cached'];
                $this->tokens = $result['tokens'];
                $this->model = $result['model'];
                $this->respondedAt = $result['created_at'];
            } else {
                $this->responseText = $result['response'];
                $this->insights = $result['insights'] ?? null;
                $this->context = $result['context'] ?? null;
                $this->cached = $result['cached'];
                $this->tokens = $result['tokens'];
                $this->model = $result['model'];
                $this->respondedAt = $result['created_at'];
                $this->viewingLogId = $result['log_id'] ?? null;
            }
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function setActiveOutletTab(int $index): void
    {
        $this->activeOutletTab = $index;
    }

    public function loadReport(int $id): void
    {
        $log = AiAnalysisLog::findOrFail($id);

        $this->responseText = $log->response_text;
        $this->insights = $this->parseInsightsFromText($log->response_text);
        $this->context = null; // Context not stored in log, will regenerate if needed
        $this->cached = true;
        $this->tokens = ['input' => $log->input_tokens, 'output' => $log->output_tokens];
        $this->model = $log->model_used;
        $this->respondedAt = $log->created_at->toDateTimeString();
        $this->viewingLogId = $log->id;
        $this->period = $log->period;
        $this->outletId = $log->outlet_id;
        $this->analysisType = $log->analysis_type;
        $this->error = '';
        $this->activeTab = 'generate';
    }

    private function parseInsightsFromText(string $content): ?array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }

        return null;
    }

    public function deleteReport(int $id): void
    {
        AiAnalysisLog::findOrFail($id)->delete();

        if ($this->viewingLogId === $id) {
            $this->resetResponse();
        }

        session()->flash('success', 'Report deleted.');
    }

    public function exportPdf()
    {
        if (empty($this->responseText)) {
            return;
        }

        $company = Company::find(Auth::user()->company_id);
        $outlet = $this->outletId ? Outlet::find($this->outletId) : null;
        $periodLabel = Carbon::createFromFormat('!Y-m', $this->period)->format('F Y');
        $outletName = $outlet?->name ?? 'All Outlets';
        $analysisType = $this->analysisType;
        $model = $this->model;

        // Build HTML content from structured insights or fall back to markdown
        $insights = $this->insights ?? $this->parseInsightsFromText($this->responseText);
        $htmlContent = $this->buildPdfContent($insights, $this->responseText);

        $pdf = Pdf::loadView('pdf.ai-analysis', compact(
            'company', 'outletName', 'periodLabel', 'analysisType', 'model', 'htmlContent'
        ))->setPaper('a4', 'portrait');

        $typeSlug = str_replace('_', '-', $this->analysisType);
        $filename = "ai-analysis-{$typeSlug}-{$this->period}.pdf";

        return response()->streamDownload(fn () => print($pdf->output()), $filename);
    }

    private function buildPdfContent(?array $insights, string $rawText): string
    {
        if (!$insights) {
            // Fall back to plain markdown
            return Str::markdown($rawText);
        }

        $html = '';

        // Headline
        if (!empty($insights['headline'])) {
            $scoreLabel = '';
            if (!empty($insights['performance_score'])) {
                $labels = [
                    'excellent' => '🟢 Excellent',
                    'good' => '🟢 Good',
                    'average' => '🟡 Average',
                    'below_average' => '🟠 Below Average',
                    'poor' => '🔴 Needs Attention',
                ];
                $scoreLabel = $labels[$insights['performance_score']] ?? ucfirst(str_replace('_', ' ', $insights['performance_score']));
            }
            $html .= "<h2 style=\"color: #4f46e5; margin-bottom: 5px;\">{$insights['headline']}</h2>";
            if ($scoreLabel) {
                $html .= "<p style=\"font-size: 12px; color: #666; margin-top: 0;\">Performance: {$scoreLabel}</p>";
            }
            $html .= "<hr style=\"border: none; border-top: 1px solid #ddd; margin: 15px 0;\">";
        }

        // Key Metrics
        if (!empty($insights['key_metrics'])) {
            $html .= "<h3>Key Metrics</h3>";
            $html .= "<table style=\"width: 100%; border-collapse: collapse; margin-bottom: 15px;\">";
            $html .= "<tr style=\"background: #f5f5f5;\">";
            foreach ($insights['key_metrics'] as $metric) {
                $trend = $metric['trend'] ?? 'flat';
                $trendIcon = $trend === 'up' ? '↑' : ($trend === 'down' ? '↓' : '→');
                $trendColor = $trend === 'up' ? '#16a34a' : ($trend === 'down' ? '#dc2626' : '#666');
                $html .= "<td style=\"padding: 10px; text-align: center; border: 1px solid #ddd;\">";
                $html .= "<div style=\"font-size: 16px; font-weight: bold;\">{$metric['value']}</div>";
                $html .= "<div style=\"font-size: 10px; color: #666;\">{$metric['label']}</div>";
                if (!empty($metric['note'])) {
                    $html .= "<div style=\"font-size: 9px; color: {$trendColor};\">{$trendIcon} {$metric['note']}</div>";
                }
                $html .= "</td>";
            }
            $html .= "</tr></table>";
        }

        // Outlets Summary (for multi-outlet)
        if (!empty($insights['outlets_summary'])) {
            $html .= "<h3>Performance by Outlet</h3>";
            $html .= "<table style=\"width: 100%; border-collapse: collapse; margin-bottom: 15px;\">";
            $html .= "<tr style=\"background: #333; color: white;\"><th style=\"padding: 6px; text-align: left;\">Outlet</th><th style=\"padding: 6px; text-align: right;\">Revenue</th><th style=\"padding: 6px; text-align: center;\">Trend</th><th style=\"padding: 6px; text-align: left;\">Note</th></tr>";
            foreach ($insights['outlets_summary'] as $outlet) {
                $trend = $outlet['trend'] ?? 'flat';
                $trendIcon = $trend === 'up' ? '↑' : ($trend === 'down' ? '↓' : '→');
                $trendColor = $trend === 'up' ? '#16a34a' : ($trend === 'down' ? '#dc2626' : '#666');
                $html .= "<tr style=\"border-bottom: 1px solid #ddd;\">";
                $html .= "<td style=\"padding: 6px;\">{$outlet['name']}</td>";
                $html .= "<td style=\"padding: 6px; text-align: right;\">{$outlet['revenue']}</td>";
                $html .= "<td style=\"padding: 6px; text-align: center; color: {$trendColor};\">{$trendIcon}</td>";
                $html .= "<td style=\"padding: 6px; font-size: 10px; color: #666;\">" . ($outlet['note'] ?? '') . "</td>";
                $html .= "</tr>";
            }
            $html .= "</table>";
        }

        // Highlights
        if (!empty($insights['highlights'])) {
            $html .= "<h3 style=\"color: #16a34a;\">✓ Highlights</h3><ul>";
            foreach ($insights['highlights'] as $highlight) {
                $html .= "<li>{$highlight}</li>";
            }
            $html .= "</ul>";
        }

        // Concerns
        if (!empty($insights['concerns'])) {
            $html .= "<h3 style=\"color: #dc2626;\">⚠ Areas of Attention</h3><ul>";
            foreach ($insights['concerns'] as $concern) {
                $html .= "<li>{$concern}</li>";
            }
            $html .= "</ul>";
        }

        // Recommendations
        if (!empty($insights['recommendations'])) {
            $html .= "<h3 style=\"color: #4f46e5;\">💡 Recommendations</h3>";
            foreach ($insights['recommendations'] as $rec) {
                $isArray = is_array($rec);
                $title = $isArray ? ($rec['title'] ?? '') : $rec;
                $description = $isArray ? ($rec['description'] ?? '') : '';
                $priority = $isArray ? ($rec['priority'] ?? 'medium') : 'medium';
                $priorityColors = ['high' => '#dc2626', 'medium' => '#d97706', 'low' => '#6b7280'];
                $color = $priorityColors[$priority] ?? '#6b7280';

                $html .= "<div style=\"margin-bottom: 10px; padding: 8px; background: #f9fafb; border-left: 3px solid {$color};\">";
                $html .= "<div style=\"font-weight: bold;\">[" . ucfirst($priority) . "] {$title}</div>";
                if ($description) {
                    $html .= "<div style=\"font-size: 11px; color: #666; margin-top: 3px;\">{$description}</div>";
                }
                $html .= "</div>";
            }
        }

        // Detailed Analysis
        if (!empty($insights['detailed_analysis'])) {
            $html .= "<hr style=\"border: none; border-top: 1px solid #ddd; margin: 20px 0;\">";
            $html .= "<h3>Detailed Analysis</h3>";
            $html .= Str::markdown($insights['detailed_analysis']);
        }

        return $html;
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function render()
    {
        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->excludingCentralKitchens()
            ->orderBy('name')
            ->get();
        $hasApiKey = !empty(AppSetting::get('openrouter_api_key'));

        $periodLabel = Carbon::createFromFormat('!Y-m', $this->period)->format('F Y');

        $analysisTypes = [
            'monthly_review'      => 'Monthly Review',
            'weekly_review'       => 'Weekly Review',
            'trend_analysis'      => 'Trend Analysis',
            'predictive_analysis' => 'Predictive Sales Forecast',
            'cost_optimization'   => 'Cost Optimization',
            'custom'              => 'Custom Question',
        ];

        $savedReports = AiAnalysisLog::with('outlet', 'requestedBy')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('livewire.analytics.index', compact(
            'outlets', 'hasApiKey', 'periodLabel', 'analysisTypes', 'savedReports'
        ))->layout('layouts.app', ['title' => 'AI Analytics']);
    }

    private function resetResponse(): void
    {
        $this->responseText = '';
        $this->insights = null;
        $this->context = null;
        $this->cached = false;
        $this->tokens = null;
        $this->model = '';
        $this->respondedAt = '';
        $this->viewingLogId = null;
        $this->isMultiOutlet = false;
        $this->outletResults = [];
        $this->activeOutletTab = 0;
        $this->error = '';
    }
}
