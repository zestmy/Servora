<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\ReportLog;
use App\Models\ReportSubscription;
use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class ReportGeneratorService
{
    protected AnalyticsDataService $analyticsService;
    protected ReportInsightsService $insightsService;
    protected ChartImageService $chartService;

    public function __construct(
        AnalyticsDataService $analyticsService,
        ReportInsightsService $insightsService,
        ChartImageService $chartService
    ) {
        $this->analyticsService = $analyticsService;
        $this->insightsService = $insightsService;
        $this->chartService = $chartService;
    }

    /**
     * Generate and send a report based on a subscription.
     */
    public function generateFromSubscription(ReportSubscription $subscription): ReportLog
    {
        $company = Company::find($subscription->company_id);
        $outlet = $subscription->outlet_id ? Outlet::find($subscription->outlet_id) : null;
        $recipientEmails = $subscription->getRecipientEmails();

        // Determine the report date/period based on report type
        $now = now();
        $reportDate = $now;
        $periodStart = null;
        $periodEnd = null;

        switch ($subscription->report_type) {
            case 'daily_sales':
                $reportDate = $now->copy()->subDay(); // Yesterday's report
                $periodStart = $reportDate->copy();
                $periodEnd = $reportDate->copy();
                break;

            case 'weekly_performance':
                $reportDate = $now->copy()->subWeek()->startOfWeek();
                $periodStart = $reportDate->copy();
                $periodEnd = $reportDate->copy()->endOfWeek();
                break;

            case 'monthly_summary':
                $reportDate = $now->copy()->subMonth()->startOfMonth();
                $periodStart = $reportDate->copy();
                $periodEnd = $reportDate->copy()->endOfMonth();
                break;
        }

        // Create the report log entry
        $log = ReportLog::create([
            'subscription_id' => $subscription->id,
            'company_id' => $subscription->company_id,
            'outlet_id' => $subscription->outlet_id,
            'report_type' => $subscription->report_type,
            'report_date' => $reportDate,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'delivery_channel' => $subscription->delivery_channel,
            'recipient_email' => implode(', ', $recipientEmails),
            'delivery_status' => 'pending',
        ]);

        try {
            // Generate the report
            $result = $this->generate(
                reportType: $subscription->report_type,
                companyId: $subscription->company_id,
                outletId: $subscription->outlet_id,
                date: $reportDate,
                includeAiInsights: $subscription->include_ai_insights
            );

            // Update log with report data
            $log->update([
                'report_data' => $result['data'],
                'ai_insights' => $result['insights'],
            ]);

            // Send the email to all recipients
            $allSuccess = true;
            $failedRecipients = [];

            foreach ($recipientEmails as $email) {
                $sendResult = $this->sendEmail(
                    recipientEmail: $email,
                    reportType: $subscription->report_type,
                    reportData: $result['data'],
                    insights: $result['insights'],
                    charts: $result['charts'],
                    companyName: $company->name,
                    outletName: $outlet?->name ?? 'All Outlets',
                    periodLabel: $result['period_label'],
                    isMultiOutlet: $result['is_multi_outlet'] ?? false,
                    outletsData: $result['outlets_data'] ?? []
                );

                if (!$sendResult['success']) {
                    $allSuccess = false;
                    $failedRecipients[] = $email;
                }
            }

            if ($allSuccess) {
                $log->markAsSent();
                $subscription->update(['last_sent_at' => now()]);
            } else {
                $log->markAsFailed('Failed to send to: ' . implode(', ', $failedRecipients));
            }

        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
            $log->markAsFailed($e->getMessage());
        }

        return $log;
    }

    /**
     * Generate a report with data, insights, and charts.
     */
    public function generate(
        string $reportType,
        int $companyId,
        ?int $outletId,
        Carbon $date,
        bool $includeAiInsights = true
    ): array {
        $periodLabel = '';
        $isMultiOutlet = false;
        $outletsData = [];

        // If no specific outlet, get data for each outlet separately
        if (!$outletId) {
            $outlets = Outlet::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->excludingCentralKitchens()
                ->orderBy('name')
                ->get();

            if ($outlets->count() > 1) {
                $isMultiOutlet = true;

                foreach ($outlets as $outlet) {
                    $outletResult = $this->generateForOutlet($reportType, $companyId, $outlet->id, $date, $includeAiInsights);
                    $outletsData[] = [
                        'outlet_id' => $outlet->id,
                        'outlet_name' => $outlet->name,
                        'data' => $outletResult['data'],
                        'insights' => $outletResult['insights'],
                        'charts' => $outletResult['charts'],
                    ];
                }

                $periodLabel = $this->getPeriodLabel($reportType, $date);

                return [
                    'data' => [], // Combined data not used for multi-outlet
                    'insights' => null,
                    'charts' => [],
                    'period_label' => $periodLabel,
                    'is_multi_outlet' => true,
                    'outlets_data' => $outletsData,
                ];
            }

            // Single outlet company - use that outlet
            if ($outlets->count() === 1) {
                $outletId = $outlets->first()->id;
            }
        }

        // Single outlet report
        $result = $this->generateForOutlet($reportType, $companyId, $outletId, $date, $includeAiInsights);
        $result['is_multi_outlet'] = false;
        $result['outlets_data'] = [];

        return $result;
    }

    /**
     * Generate report data for a specific outlet.
     */
    protected function generateForOutlet(
        string $reportType,
        int $companyId,
        ?int $outletId,
        Carbon $date,
        bool $includeAiInsights
    ): array {
        $data = [];
        $insights = null;
        $charts = [];
        $periodLabel = '';

        switch ($reportType) {
            case 'daily_sales':
                $data = $this->analyticsService->getDailySalesData($companyId, $outletId, $date);
                $periodLabel = $date->format('l, j F Y');

                // Generate charts
                if (!empty($data['by_meal_period'])) {
                    $charts['meal_period'] = $this->chartService->mealPeriodChart($data['by_meal_period']);
                }
                if (!empty($data['sales_by_category'])) {
                    $charts['sales_by_category'] = $this->chartService->salesByCategoryChart($data['sales_by_category']);
                }

                // Generate AI insights
                if ($includeAiInsights) {
                    $insightResult = $this->insightsService->generateDailyInsights($data);
                    if ($insightResult['success']) {
                        $insights = $insightResult['insights'];
                    }
                }
                break;

            case 'weekly_performance':
                $weekStart = $date->copy()->startOfWeek();
                $data = $this->analyticsService->getWeeklySalesData($companyId, $outletId, $weekStart);
                $periodLabel = $weekStart->format('j M') . ' - ' . $weekStart->copy()->endOfWeek()->format('j M Y');

                // Generate charts
                if (!empty($data['daily_breakdown'])) {
                    $charts['daily_revenue'] = $this->chartService->dailyRevenueChart($data['daily_breakdown']);
                }
                if (!empty($data['by_meal_period'])) {
                    $charts['meal_period'] = $this->chartService->mealPeriodPieChart($data['by_meal_period']);
                }
                if (!empty($data['sales_by_category'])) {
                    $charts['sales_by_category'] = $this->chartService->salesByCategoryChart($data['sales_by_category']);
                }

                // Generate AI insights
                if ($includeAiInsights) {
                    $insightResult = $this->insightsService->generateWeeklyInsights($data);
                    if ($insightResult['success']) {
                        $insights = $insightResult['insights'];
                    }
                }
                break;

            case 'monthly_summary':
                $monthStart = $date->copy()->startOfMonth();
                $data = $this->analyticsService->getMonthlySalesData($companyId, $outletId, $monthStart);
                $periodLabel = $monthStart->format('F Y');

                // Generate charts
                if (!empty($data['daily_breakdown'])) {
                    $charts['daily_revenue'] = $this->chartService->dailyRevenueChart($data['daily_breakdown']);
                }
                if (!empty($data['weekly_breakdown'])) {
                    $charts['weekly'] = $this->chartService->weeklyComparisonChart($data['weekly_breakdown']);
                }
                if (!empty($data['by_meal_period'])) {
                    $charts['meal_period'] = $this->chartService->mealPeriodPieChart($data['by_meal_period']);
                }
                if (!empty($data['sales_by_category'])) {
                    $charts['sales_by_category'] = $this->chartService->salesByCategoryChart($data['sales_by_category']);
                }

                // Generate AI insights
                if ($includeAiInsights) {
                    $insightResult = $this->insightsService->generateMonthlyInsights($data);
                    if ($insightResult['success']) {
                        $insights = $insightResult['insights'];
                    }
                }
                break;
        }

        return [
            'data' => $data,
            'insights' => $insights,
            'charts' => $charts,
            'period_label' => $periodLabel,
        ];
    }

    /**
     * Get period label for report type.
     */
    protected function getPeriodLabel(string $reportType, Carbon $date): string
    {
        return match ($reportType) {
            'daily_sales' => $date->format('l, j F Y'),
            'weekly_performance' => $date->copy()->startOfWeek()->format('j M') . ' - ' . $date->copy()->startOfWeek()->endOfWeek()->format('j M Y'),
            'monthly_summary' => $date->copy()->startOfMonth()->format('F Y'),
            default => $date->format('j F Y'),
        };
    }

    /**
     * Send the report email via EngineMailer.
     */
    protected function sendEmail(
        string $recipientEmail,
        string $reportType,
        array $reportData,
        ?array $insights,
        array $charts,
        string $companyName,
        string $outletName,
        string $periodLabel,
        bool $isMultiOutlet = false,
        array $outletsData = []
    ): array {
        $view = match ($reportType) {
            'daily_sales' => 'emails.reports.daily',
            'weekly_performance' => 'emails.reports.weekly',
            'monthly_summary' => 'emails.reports.monthly',
            default => 'emails.reports.daily',
        };

        $subject = match ($reportType) {
            'daily_sales' => "Daily Sales Report - {$outletName} - {$periodLabel}",
            'weekly_performance' => "Weekly Performance Report - {$outletName} - {$periodLabel}",
            'monthly_summary' => "Monthly Summary Report - {$outletName} - {$periodLabel}",
            default => "Analytics Report - {$outletName}",
        };

        // Render the email HTML
        $html = View::make($view, [
            'reportType' => $reportType,
            'reportData' => $reportData,
            'insights' => $insights,
            'charts' => $charts,
            'outletName' => $outletName,
            'companyName' => $companyName,
            'periodLabel' => $periodLabel,
            'isMultiOutlet' => $isMultiOutlet,
            'outletsData' => $outletsData,
        ])->render();

        // Get sender email from settings
        $senderEmail = AppSetting::get('enginemailer_sender_email');
        if (!$senderEmail) {
            return ['success' => false, 'message' => 'Sender email not configured'];
        }

        // Send via EngineMailer
        return EngineMailerService::send(
            toEmail: $recipientEmail,
            senderEmail: $senderEmail,
            senderName: $companyName,
            subject: $subject,
            htmlContent: $html
        );
    }

    /**
     * Generate and send a test report (for preview).
     */
    public function sendTestReport(
        string $reportType,
        int $companyId,
        ?int $outletId,
        string $recipientEmail,
        bool $includeAiInsights = true
    ): array {
        $company = Company::find($companyId);
        $outlet = $outletId ? Outlet::find($outletId) : null;

        $date = match ($reportType) {
            'daily_sales' => now()->subDay(),
            'weekly_performance' => now()->subWeek()->startOfWeek(),
            'monthly_summary' => now()->subMonth()->startOfMonth(),
            default => now()->subDay(),
        };

        try {
            $result = $this->generate($reportType, $companyId, $outletId, $date, $includeAiInsights);

            $sendResult = $this->sendEmail(
                recipientEmail: $recipientEmail,
                reportType: $reportType,
                reportData: $result['data'],
                insights: $result['insights'],
                charts: $result['charts'],
                companyName: $company->name,
                outletName: $outlet?->name ?? 'All Outlets',
                periodLabel: $result['period_label'],
                isMultiOutlet: $result['is_multi_outlet'] ?? false,
                outletsData: $result['outlets_data'] ?? []
            );

            return $sendResult;
        } catch (\Exception $e) {
            Log::error('Test report failed', [
                'error' => $e->getMessage(),
                'report_type' => $reportType,
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
