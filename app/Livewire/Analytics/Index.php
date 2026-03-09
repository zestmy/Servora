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
    public bool $cached = false;
    public ?array $tokens = null;
    public string $model = '';
    public string $respondedAt = '';
    public ?int $viewingLogId = null;

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
        $this->period = Carbon::createFromFormat('Y-m', $this->period)->subMonth()->format('Y-m');
        $this->resetResponse();
    }

    public function nextMonth(): void
    {
        $this->period = Carbon::createFromFormat('Y-m', $this->period)->addMonth()->format('Y-m');
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
        $this->viewingLogId = null;

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

            $this->responseText = $result['response'];
            $this->cached = $result['cached'];
            $this->tokens = $result['tokens'];
            $this->model = $result['model'];
            $this->respondedAt = $result['created_at'];
            $this->viewingLogId = $result['log_id'] ?? null;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function loadReport(int $id): void
    {
        $log = AiAnalysisLog::findOrFail($id);

        $this->responseText = $log->response_text;
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
        $periodLabel = Carbon::createFromFormat('Y-m', $this->period)->format('F Y');
        $outletName = $outlet?->name ?? 'All Outlets';
        $analysisType = $this->analysisType;
        $model = $this->model;
        $htmlContent = Str::markdown($this->responseText);

        $pdf = Pdf::loadView('pdf.ai-analysis', compact(
            'company', 'outletName', 'periodLabel', 'analysisType', 'model', 'htmlContent'
        ))->setPaper('a4', 'portrait');

        $typeSlug = str_replace('_', '-', $this->analysisType);
        $filename = "ai-analysis-{$typeSlug}-{$this->period}.pdf";

        return response()->streamDownload(fn () => print($pdf->output()), $filename);
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function render()
    {
        $outlets = Outlet::where('company_id', Auth::user()->company_id)->orderBy('name')->get();
        $aiProvider = AppSetting::get('ai_provider', 'anthropic');
        $hasApiKey = $aiProvider === 'openrouter'
            ? ! empty(AppSetting::get('openrouter_api_key'))
            : ! empty(AppSetting::get('anthropic_api_key'));

        $periodLabel = Carbon::createFromFormat('Y-m', $this->period)->format('F Y');

        $analysisTypes = [
            'monthly_review'    => 'Monthly Review',
            'weekly_review'     => 'Weekly Review',
            'trend_analysis'    => 'Trend Analysis',
            'cost_optimization' => 'Cost Optimization',
            'custom'            => 'Custom Question',
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
        $this->cached = false;
        $this->tokens = null;
        $this->model = '';
        $this->respondedAt = '';
        $this->viewingLogId = null;
        $this->error = '';
    }
}
