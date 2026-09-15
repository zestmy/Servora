<?php

namespace App\Livewire\Reports\Management;

use App\Models\Employee;
use App\Services\Reports\WeeklyWipReview as WipReviewReport;
use App\Traits\ScopesToActiveOutlet;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * WIP meeting review — one week (or month) against the one before, as a
 * scrolling report or a full-screen slide deck (see the view).
 *
 * Opens on the last COMPLETE week or month: the meeting reviews the period
 * just closed, and one still in progress would read as a collapse in sales
 * every Monday, or every 1st.
 */
class WeeklyWipReview extends Component
{
    use ScopesToActiveOutlet;

    /** 'week' or 'month'. */
    public string $mode = WipReviewReport::WEEK;

    /** Monday of the reviewed week, Y-m-d. */
    public string $week = '';

    /** Weeks in the trend. Untyped: a select posts a string. */
    public $weeks = 8;

    /** The reviewed month, Y-m (what an <input type="month"> posts). */
    public string $month = '';

    /** Months in the trend. */
    public $months = 3;

    /** Count draft payroll runs in labour cost, not only approved and paid. */
    public bool $includeDraftPayroll = false;

    public string $outletFilter = '';

    public function mount(): void
    {
        $this->week  = $this->lastCompleteWeek()->toDateString();
        $this->month = $this->lastCompleteMonth()->format('Y-m');
    }

    public function updatedMode(): void
    {
        if (! in_array($this->mode, [WipReviewReport::WEEK, WipReviewReport::MONTH], true)) {
            $this->mode = WipReviewReport::WEEK;
        }
    }

    public function updatedWeek(): void
    {
        $this->week = $this->normaliseWeek($this->week)->toDateString();
    }

    public function updatedMonth(): void
    {
        $this->month = $this->normaliseMonth($this->month)->format('Y-m');
    }

    public function updatedWeeks(): void
    {
        if (! in_array((int) $this->weeks, WipReviewReport::WEEK_OPTIONS, true)) {
            $this->weeks = 8;
        }
    }

    public function updatedMonths(): void
    {
        if (! in_array((int) $this->months, WipReviewReport::MONTH_OPTIONS, true)) {
            $this->months = 3;
        }
    }

    public function previousPeriod(): void
    {
        $this->isMonthly()
            ? $this->month = $this->normaliseMonth($this->month)->subMonthNoOverflow()->format('Y-m')
            : $this->week  = $this->normaliseWeek($this->week)->subWeek()->toDateString();
    }

    public function nextPeriod(): void
    {
        $this->isMonthly()
            ? $this->month = $this->normaliseMonth($this->month)->addMonthNoOverflow()->format('Y-m')
            : $this->week  = $this->normaliseWeek($this->week)->addWeek()->toDateString();
    }

    /** Trend bars are clickable: review that period instead. */
    public function reviewPeriod(string $start): void
    {
        $this->isMonthly()
            ? $this->month = $this->normaliseMonth($start)->format('Y-m')
            : $this->week  = $this->normaliseWeek($start)->toDateString();
    }

    private function isMonthly(): bool
    {
        return $this->mode === WipReviewReport::MONTH;
    }

    /** Any date → the Monday of its week, never later than the current week. */
    private function normaliseWeek(string $date): Carbon
    {
        try {
            $monday = Carbon::parse($date)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        } catch (\Throwable) {
            return $this->lastCompleteWeek();
        }

        $latest = now()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();

        return $monday->gt($latest) ? $latest : $monday;
    }

    /** 'Y-m' or any date → the 1st of its month, never later than this month. */
    private function normaliseMonth(string $value): Carbon
    {
        try {
            $first = (preg_match('/^\d{4}-\d{2}$/', $value) ? Carbon::createFromFormat('!Y-m', $value) : Carbon::parse($value))
                ->startOfMonth()->startOfDay();
        } catch (\Throwable) {
            return $this->lastCompleteMonth();
        }

        $latest = now()->startOfMonth()->startOfDay();

        return $first->gt($latest) ? $latest : $first;
    }

    private function lastCompleteWeek(): Carbon
    {
        return now()->startOfWeek(CarbonInterface::MONDAY)->subWeek()->startOfDay();
    }

    private function lastCompleteMonth(): Carbon
    {
        return now()->startOfMonth()->subMonthNoOverflow()->startOfDay();
    }

    public function render()
    {
        $selected  = $this->selectedOutletId($this->outletFilter);
        $outletIds = $selected !== null ? [$selected] : $this->availableOutletIds();
        $outlets   = $this->filterableOutlets();
        $monthly   = $this->isMonthly();
        $anchor    = $monthly ? $this->normaliseMonth($this->month) : $this->normaliseWeek($this->week);

        $report = app(WipReviewReport::class)->build(
            (int) Auth::user()->company_id,
            $outletIds,
            $anchor,
            (int) ($monthly ? $this->months : $this->weeks),
            $monthly ? WipReviewReport::MONTH : WipReviewReport::WEEK,
            $this->includeDraftPayroll,
            Employee::canViewPay(Auth::user()),
        );

        $latest = $monthly
            ? now()->startOfMonth()->startOfDay()
            : now()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();

        return view('livewire.reports.management.weekly-wip-review', [
            'report'       => $report,
            'outlets'      => $outlets,
            'weekOptions'  => WipReviewReport::WEEK_OPTIONS,
            'monthOptions' => WipReviewReport::MONTH_OPTIONS,
            'isLatest'     => $anchor->gte($latest),
            'scopeLabel'   => $selected !== null
                ? ($outlets->firstWhere('id', $selected)?->name ?? 'One outlet')
                : 'All outlets',
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'WIP Review']);
    }
}
