<?php

namespace App\Http\Controllers;

use App\Models\ReportLog;
use App\Services\ReportGeneratorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

/**
 * PDF download for a scheduled report (Settings > Scheduled Reports history).
 * Renders the SAME email template the recipients received, so the printout
 * matches the emailed report.
 */
class ReportPdfController extends Controller
{
    public function download(int $logId, ReportGeneratorService $service)
    {
        // CompanyScope on ReportLog keeps this tenant-safe.
        $log = ReportLog::with(['subscription', 'outlet', 'company'])->findOrFail($logId);

        // Rebuild the report the same way a Resend does. Data and charts are
        // deterministic (SQL + chart-URL building); AI insights are reused
        // from the log when stored (single-outlet sends) so the PDF matches
        // the email without a fresh AI call. Multi-outlet logs don't store
        // per-outlet insights, so those regenerate — same cost as a Resend.
        $hasStoredInsights = ! empty($log->ai_insights);
        $includeAi = $hasStoredInsights
            ? false
            : (bool) ($log->subscription?->include_ai_insights ?? true);

        $result = $service->generate(
            reportType: $log->report_type,
            companyId: $log->company_id,
            outletId: $log->outlet_id,
            date: Carbon::parse($log->report_date),
            includeAiInsights: $includeAi,
        );

        $view = match ($log->report_type) {
            'weekly_performance' => 'emails.reports.weekly',
            'monthly_summary'    => 'emails.reports.monthly',
            default              => 'emails.reports.daily',
        };

        $outletName = $log->outlet?->name ?? 'All Outlets';

        $outletsData = $result['outlets_data'] ?? [];
        foreach ($outletsData as $i => $od) {
            $outletsData[$i]['charts'] = $this->inlineCharts($od['charts'] ?? []);
        }

        $html = View::make($view, [
            'reportType'    => $log->report_type,
            'reportData'    => $result['data'],
            'insights'      => $hasStoredInsights ? $log->ai_insights : $result['insights'],
            'charts'        => $this->inlineCharts($result['charts'] ?? []),
            'outletName'    => $outletName,
            'companyName'   => $log->company?->name ?? '',
            'periodLabel'   => $result['period_label'],
            'isMultiOutlet' => $result['is_multi_outlet'] ?? false,
            'outletsData'   => $outletsData,
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        $filename = $this->safeFilename(sprintf(
            '%s-%s-%s.pdf',
            str_replace('_', '-', $log->report_type),
            $outletName,
            Carbon::parse($log->report_date)->format('Y-m-d')
        ));

        return $pdf->download($filename);
    }

    /**
     * Fetch remote chart images (QuickChart URLs) and inline them as data
     * URIs — DomPDF runs with remote fetching disabled. A chart that fails to
     * fetch is dropped; the templates already guard with @if(!empty(...)).
     */
    private function inlineCharts(array $charts): array
    {
        foreach ($charts as $key => $url) {
            $charts[$key] = $this->toDataUri($url);
        }

        return array_filter($charts);
    }

    private function toDataUri(?string $url): ?string
    {
        if (! $url || ! str_starts_with($url, 'http')) {
            return $url;
        }

        try {
            $resp = Http::timeout(10)->get($url);
            if ($resp->successful()) {
                $mime = $resp->header('Content-Type') ?: 'image/png';
                return 'data:' . $mime . ';base64,' . base64_encode($resp->body());
            }
        } catch (\Throwable) {
            // fall through — chart dropped from the PDF
        }

        return null;
    }

    /** Strip characters Symfony rejects in Content-Disposition filenames. */
    private function safeFilename(string $name): string
    {
        return str_replace(['/', '\\', '%'], '-', $name);
    }
}
