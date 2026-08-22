<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\ReportLog;
use App\Models\ReportSubscription;
use App\Models\SalesClosure;
use App\Models\SalesRecord;
use App\Scopes\CompanyScope;
use App\Services\Hr\DocumentExpiry;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
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
     *
     * @param bool $force Bypass the data-completeness guardrail (manual resends).
     * @param Carbon|null $reportDateOverride Anchor the report to a specific past
     *        period instead of deriving it from today (used when resending a log).
     */
    public function generateFromSubscription(ReportSubscription $subscription, bool $force = false, ?Carbon $reportDateOverride = null): ReportLog
    {
        $company = Company::find($subscription->company_id);
        $outlet = $subscription->outlet_id ? Outlet::find($subscription->outlet_id) : null;
        $recipientEmails = $subscription->getRecipientEmails();

        // Determine the report date/period based on report type
        $now = now();
        $anchor = $reportDateOverride?->copy();
        $reportDate = $now;
        $periodStart = null;
        $periodEnd = null;

        switch ($subscription->report_type) {
            case 'daily_sales':
                $reportDate = $anchor ?? $now->copy()->subDay(); // Yesterday's report
                $periodStart = $reportDate->copy();
                $periodEnd = $reportDate->copy();
                break;

            case 'weekly_performance':
                $reportDate = ($anchor ?? $now->copy()->subWeek())->startOfWeek();
                $periodStart = $reportDate->copy();
                $periodEnd = $reportDate->copy()->endOfWeek();
                break;

            case 'monthly_summary':
                $reportDate = ($anchor ?? $now->copy()->subMonth())->startOfMonth();
                $periodStart = $reportDate->copy();
                $periodEnd = $reportDate->copy()->endOfMonth();
                break;

            case 'hr_document_expiry':
                // Forward-looking, not a closed period: it reports what lapses
                // in the NEXT warning window, so the report date is today and
                // the period runs from today to the end of that window.
                $reportDate = $anchor ?? $now->copy();
                $periodStart = $reportDate->copy();
                $periodEnd = $reportDate->copy()->addDays(DocumentExpiry::WARNING_DAYS);
                break;
        }

        // Guardrail: don't send reports built on empty or incomplete data. Checked
        // before any generation so retries cost a few cheap queries, no AI calls.
        // It is sales-shaped (it asks whether takings have landed), so an HR
        // report must skip it or it would be held back every single run.
        if (!$force && ! $this->skipsDataCompleteness($subscription->report_type)) {
            $issue = $this->checkDataCompleteness($subscription->company_id, $subscription->outlet_id, $periodStart, $periodEnd);

            if ($issue !== null) {
                // Reuse the existing skip log for this period so the 15-minute
                // scheduler doesn't pile up duplicate entries all day.
                $log = ReportLog::withoutGlobalScopes()
                    ->where('subscription_id', $subscription->id)
                    ->whereDate('report_date', $reportDate->toDateString())
                    ->where('delivery_status', 'skipped')
                    ->first();

                if ($log) {
                    $log->update(['error_message' => $issue]);
                } else {
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
                        'delivery_status' => 'skipped',
                        'error_message' => $issue,
                    ]);
                }

                return $log;
            }
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
                includeAiInsights: $subscription->include_ai_insights,
                // Empty for "all outlets"; a subset when the subscriber picked
                // specific branches. outlet_id is only set when there is
                // exactly one, so the two never contradict each other.
                outletIds: $subscription->outletIds(),
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
                // A manual resend of an old period shouldn't suppress today's
                // scheduled send, so only stamp last_sent_at on scheduled runs.
                if (!$force) {
                    $subscription->update(['last_sent_at' => now()]);
                }
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
    /**
     * @param  array<int, int>  $outletIds  Restrict a company-wide report to
     *         these outlets. Empty means every active outlet — the historical
     *         meaning of a null $outletId, and still what an "All Outlets"
     *         subscription stores.
     */
    public function generate(
        string $reportType,
        int $companyId,
        ?int $outletId,
        Carbon $date,
        bool $includeAiInsights = true,
        array $outletIds = []
    ): array {
        $periodLabel = '';
        $isMultiOutlet = false;
        $outletsData = [];

        // If no specific outlet, get data for each outlet separately.
        // The HR expiry report opts out: "All Outlets" there means one list
        // covering the whole company, with the outlet shown per person —
        // splitting it per outlet would mail the same manager several lists to
        // reconcile by hand, and each would omit the head-office staff.
        if (!$outletId && ! $this->reportsWholeCompany($reportType)) {
            $outlets = Outlet::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->excludingCentralKitchens()
                // A subscription may name a subset. Still scoped to the
                // company and to active outlets, so a stale id in the pivot
                // cannot pull in an outlet the subscriber should not see.
                ->when($outletIds !== [], fn ($q) => $q->whereIn('id', $outletIds))
                ->orderBy('name')
                ->get();

            // One outlet left after filtering is a single-outlet report, not a
            // multi-outlet one with a single section — otherwise a two-outlet
            // subscription that loses an outlet silently changes shape.
            if ($outlets->count() === 1) {
                $single = $this->generateForOutlet($reportType, $companyId, $outlets->first()->id, $date, $includeAiInsights);

                return array_merge($single, [
                    'period_label'    => $this->getPeriodLabel($reportType, $date),
                    'is_multi_outlet' => false,
                ]);
            }

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

            case 'hr_document_expiry':
                $data = $this->documentExpiryData($companyId, $outletId, $date);
                $periodLabel = $this->getPeriodLabel($reportType, $date);
                // No AI insights: this report is a work list, and a paragraph
                // of generated commentary above someone's lapsed typhoid card
                // adds nothing an HR manager needs.
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
     * Report types that are not built on takings, so the sales-completeness
     * guardrail must not hold them back.
     */
    protected function skipsDataCompleteness(string $reportType): bool
    {
        return $reportType === 'hr_document_expiry';
    }

    /** Report types where "All Outlets" means one company-wide list, not a per-outlet fan-out. */
    protected function reportsWholeCompany(string $reportType): bool
    {
        return $reportType === 'hr_document_expiry';
    }

    /**
     * Employee document and training expiries, in the same shape the Employees
     * screen card renders — both go through DocumentExpiry so the email and the
     * screen can never disagree about who is due.
     *
     * Runs on the scheduler with no authenticated user, so CompanyScope is
     * dropped by hand and the company filtered explicitly.
     */
    protected function documentExpiryData(int $companyId, ?int $outletId, Carbon $date): array
    {
        $employees = Employee::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId));

        $summary = app(DocumentExpiry::class)->summarise($employees, $companyId, $date->copy()->startOfDay());

        // Flattened for storage on the log: report_data is a json column, and
        // Carbon instances would come back as strings anyway.
        return [
            'documents'    => $summary['documents'],
            'counts'       => $summary['counts'],
            'employees'    => $summary['employees'],
            'warning_days' => $summary['warning_days'],
            'as_of'        => $summary['as_of']->toDateString(),
            'rows'         => $summary['rows']->map(fn ($r) => [
                'employee_id' => $r['employee_id'],
                'name'        => $r['name'],
                'staff_id'    => $r['staff_id'],
                'outlet'      => $r['outlet'],
                'document'    => $r['document'],
                'state'       => $r['state'],
                'expires_on'  => $r['expires_on']?->toDateString(),
                'days'        => $r['days'],
            ])->all(),
        ];
    }

    /**
     * Check whether sales data for the report period is complete enough to send.
     *
     * Returns null when the data is fine, or a human-readable reason to skip:
     * - a day in the period has neither a sales record nor a registered closure
     * - the whole period has zero revenue
     * For "All Outlets" subscriptions every active outlet is checked.
     */
    public function checkDataCompleteness(int $companyId, ?int $outletId, Carbon $periodStart, Carbon $periodEnd): ?string
    {
        $outlets = Outlet::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->when($outletId, fn ($q) => $q->where('id', $outletId))
            ->when(!$outletId, fn ($q) => $q->where('is_active', true)->excludingCentralKitchens())
            ->orderBy('name')
            ->get();

        if ($outlets->isEmpty()) {
            return 'No active outlets found for this company.';
        }

        // Only judge days that have fully passed
        $end = $periodEnd->copy()->min(now()->subDay());
        if ($end->lt($periodStart)) {
            return 'The report period has no completed days yet.';
        }

        $issues = [];

        foreach ($outlets as $outlet) {
            $revenueByDate = SalesRecord::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where('outlet_id', $outlet->id)
                ->whereBetween('sale_date', [$periodStart->toDateString(), $end->toDateString()])
                ->selectRaw('sale_date, SUM(total_revenue) as revenue')
                ->groupBy('sale_date')
                ->toBase()
                ->pluck('revenue', 'sale_date');

            $closureDates = SalesClosure::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where(fn ($q) => $q->where('outlet_id', $outlet->id)->orWhereNull('outlet_id'))
                ->whereBetween('closure_date', [$periodStart->toDateString(), $end->toDateString()])
                ->toBase()
                ->pluck('closure_date')
                ->map(fn ($d) => substr((string) $d, 0, 10))
                ->all();

            $totalRevenue = 0.0;
            $missingDays = [];
            $closedDays = 0;
            $totalDays = 0;

            foreach (CarbonPeriod::create($periodStart, $end) as $day) {
                $totalDays++;
                $key = $day->toDateString();
                if (isset($revenueByDate[$key])) {
                    $totalRevenue += (float) $revenueByDate[$key];
                } elseif (in_array($key, $closureDates, true)) {
                    $closedDays++;
                } else {
                    $missingDays[] = $day->format('j M');
                }
            }

            if ($totalRevenue <= 0 && $closedDays === $totalDays) {
                $issues[] = "{$outlet->name}: closed for the entire period (closure recorded)";
            } elseif ($totalRevenue <= 0) {
                $issues[] = "{$outlet->name}: no sales recorded for this period";
            } elseif (!empty($missingDays)) {
                $shown = array_slice($missingDays, 0, 5);
                $more = count($missingDays) - count($shown);
                $issues[] = "{$outlet->name}: no sales entry or closure for " . implode(', ', $shown) . ($more > 0 ? " (+{$more} more)" : '');
            }
        }

        if (empty($issues)) {
            return null;
        }

        return 'Data incomplete — ' . implode('; ', $issues)
            . '. The report will send automatically once data is complete, or use Resend to send it as-is.';
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
            // Forward-looking: the window it is warning about, not a past period.
            'hr_document_expiry' => 'next ' . DocumentExpiry::WARNING_DAYS . ' days, as at ' . $date->format('j M Y'),
            default => $date->format('j F Y'),
        };
    }

    /** "3 expired, 5 expiring — Staff Documents (Outlet A)". */
    protected function expirySubject(array $reportData, string $outletName): string
    {
        $counts  = $reportData['counts'] ?? [];
        $expired = $counts[DocumentExpiry::EXPIRED] ?? 0;
        $soon    = $counts[DocumentExpiry::EXPIRING] ?? 0;

        $lead = match (true) {
            $expired > 0 && $soon > 0 => "{$expired} expired, {$soon} expiring",
            $expired > 0              => "{$expired} expired",
            $soon > 0                 => "{$soon} expiring",
            default                   => 'All in date',
        };

        return "{$lead} — Staff Documents ({$outletName})";
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
            'hr_document_expiry' => 'emails.reports.hr-document-expiry',
            default => 'emails.reports.daily',
        };

        $subject = match ($reportType) {
            'daily_sales' => "Daily Sales Report - {$outletName} - {$periodLabel}",
            'weekly_performance' => "Weekly Performance Report - {$outletName} - {$periodLabel}",
            'monthly_summary' => "Monthly Summary Report - {$outletName} - {$periodLabel}",
            // Lead with the count: this one is read on a phone, and whether it
            // needs opening at all is the first question.
            'hr_document_expiry' => $this->expirySubject($reportData, $outletName),
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
            // Anchored to today, not a past period — it reports what is coming.
            'hr_document_expiry' => now(),
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
