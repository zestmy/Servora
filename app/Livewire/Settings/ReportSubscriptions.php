<?php

namespace App\Livewire\Settings;

use App\Models\Outlet;
use App\Models\ReportLog;
use App\Models\ReportSubscription;
use App\Services\ReportGeneratorService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ReportSubscriptions extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $report_type = 'daily_sales';
    public string $frequency = 'daily';
    public ?int $outlet_id = null;
    public string $delivery_channel = 'email';
    public string $delivery_time = '06:00';
    public ?int $delivery_day = null;
    public bool $is_active = true;
    public bool $include_ai_insights = true;

    public bool $showTestModal = false;
    public string $testReportType = 'daily_sales';
    public ?int $testOutletId = null;
    public string $testEmail = '';
    public bool $testIncludeAi = true;
    public string $testResult = '';
    public bool $testSuccess = false;
    public bool $testSending = false;

    protected function rules(): array
    {
        return [
            'report_type' => 'required|in:daily_sales,weekly_performance,monthly_summary',
            'frequency' => 'required|in:daily,weekly,monthly',
            'outlet_id' => 'nullable|exists:outlets,id',
            'delivery_channel' => 'required|in:email',
            'delivery_time' => 'required|date_format:H:i',
            'delivery_day' => 'nullable|integer|min:1|max:31',
            'is_active' => 'boolean',
            'include_ai_insights' => 'boolean',
        ];
    }

    public function mount(): void
    {
        $this->testEmail = Auth::user()->email;
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $subscription = ReportSubscription::findOrFail($id);

        $this->editingId = $subscription->id;
        $this->report_type = $subscription->report_type;
        $this->frequency = $subscription->frequency;
        $this->outlet_id = $subscription->outlet_id;
        $this->delivery_channel = $subscription->delivery_channel;
        $this->delivery_time = $subscription->delivery_time?->format('H:i') ?? '06:00';
        $this->delivery_day = $subscription->delivery_day;
        $this->is_active = $subscription->is_active;
        $this->include_ai_insights = $subscription->include_ai_insights;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'report_type' => $this->report_type,
            'frequency' => $this->frequency,
            'outlet_id' => $this->outlet_id ?: null,
            'delivery_channel' => $this->delivery_channel,
            'delivery_time' => $this->delivery_time,
            'delivery_day' => $this->frequency !== 'daily' ? $this->delivery_day : null,
            'is_active' => $this->is_active,
            'include_ai_insights' => $this->include_ai_insights,
        ];

        if ($this->editingId) {
            ReportSubscription::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Report subscription updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $data['user_id'] = Auth::id();
            ReportSubscription::create($data);
            session()->flash('success', 'Report subscription created.');
        }

        $this->closeModal();
    }

    public function toggleActive(int $id): void
    {
        $subscription = ReportSubscription::findOrFail($id);
        $subscription->update(['is_active' => !$subscription->is_active]);
        session()->flash('success', $subscription->is_active ? 'Subscription activated.' : 'Subscription paused.');
    }

    public function delete(int $id): void
    {
        ReportSubscription::findOrFail($id)->delete();
        session()->flash('success', 'Report subscription deleted.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function openTestModal(): void
    {
        $this->testReportType = 'daily_sales';
        $this->testOutletId = null;
        $this->testEmail = Auth::user()->email;
        $this->testIncludeAi = true;
        $this->testResult = '';
        $this->testSuccess = false;
        $this->showTestModal = true;
    }

    public function closeTestModal(): void
    {
        $this->showTestModal = false;
        $this->testResult = '';
    }

    public function sendTestReport(ReportGeneratorService $reportService): void
    {
        $this->testResult = '';
        $this->testSuccess = false;
        $this->testSending = true;

        try {
            $result = $reportService->sendTestReport(
                reportType: $this->testReportType,
                companyId: Auth::user()->company_id,
                outletId: $this->testOutletId,
                recipientEmail: $this->testEmail,
                includeAiInsights: $this->testIncludeAi
            );

            $this->testSuccess = $result['success'];
            $this->testResult = $result['message'];
        } catch (\Exception $e) {
            $this->testResult = 'Error: ' . $e->getMessage();
        }

        $this->testSending = false;
    }

    public function render()
    {
        $subscriptions = ReportSubscription::with(['outlet', 'user'])
            ->orderByDesc('created_at')
            ->paginate(15);

        $outlets = Outlet::where('company_id', Auth::user()->company_id)->orderBy('name')->get();

        $recentLogs = ReportLog::with(['outlet', 'subscription'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('livewire.settings.report-subscriptions', compact('subscriptions', 'outlets', 'recentLogs'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Report Subscriptions']);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->report_type = 'daily_sales';
        $this->frequency = 'daily';
        $this->outlet_id = null;
        $this->delivery_channel = 'email';
        $this->delivery_time = '06:00';
        $this->delivery_day = null;
        $this->is_active = true;
        $this->include_ai_insights = true;
        $this->resetValidation();
    }

    public function getReportTypeOptions(): array
    {
        return [
            'daily_sales' => 'Daily Sales Report',
            'weekly_performance' => 'Weekly Performance Report',
            'monthly_summary' => 'Monthly Summary Report',
        ];
    }

    public function getFrequencyOptions(): array
    {
        return [
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
        ];
    }

    public function getDayOfWeekOptions(): array
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];
    }
}
