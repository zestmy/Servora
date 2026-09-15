<?php

namespace App\Livewire\Reports\Management;

use App\Models\Employee;
use App\Services\Reports\WeeklyWipReview as WipReviewReport;
use App\Services\Reports\WipReviewParameters as Params;
use App\Traits\ScopesToActiveOutlet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * WIP meeting review — one week (or month) against the one before, as a
 * scrolling report, a full-screen slide deck (see the view) or a PDF of the
 * whole thing (WipReviewPdfController, fed the same choices).
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
        $this->week  = Params::lastCompleteWeek()->toDateString();
        $this->month = Params::lastCompleteMonth()->format('Y-m');
    }

    public function updatedMode(): void
    {
        $this->mode = Params::mode($this->mode);
    }

    public function updatedWeek(): void
    {
        $this->week = Params::week($this->week)->toDateString();
    }

    public function updatedMonth(): void
    {
        $this->month = Params::month($this->month)->format('Y-m');
    }

    public function updatedWeeks(): void
    {
        $this->weeks = Params::weeks($this->weeks);
    }

    public function updatedMonths(): void
    {
        $this->months = Params::months($this->months);
    }

    public function previousPeriod(): void
    {
        $this->isMonthly()
            ? $this->month = Params::month($this->month)->subMonthNoOverflow()->format('Y-m')
            : $this->week  = Params::week($this->week)->subWeek()->toDateString();
    }

    public function nextPeriod(): void
    {
        $this->isMonthly()
            ? $this->month = Params::month(Params::month($this->month)->addMonthNoOverflow()->toDateString())->format('Y-m')
            : $this->week  = Params::week(Params::week($this->week)->addWeek()->toDateString())->toDateString();
    }

    /** Trend bars are clickable: review that period instead. */
    public function reviewPeriod(string $start): void
    {
        $this->isMonthly()
            ? $this->month = Params::month($start)->format('Y-m')
            : $this->week  = Params::week($start)->toDateString();
    }

    private function isMonthly(): bool
    {
        return $this->mode === WipReviewReport::MONTH;
    }

    private function anchor(): Carbon
    {
        return $this->isMonthly() ? Params::month($this->month) : Params::week($this->week);
    }

    public function render()
    {
        $selected  = $this->selectedOutletId($this->outletFilter);
        $outletIds = $selected !== null ? [$selected] : $this->availableOutletIds();
        $outlets   = $this->filterableOutlets();
        $monthly   = $this->isMonthly();
        $anchor    = $this->anchor();
        $count     = $monthly ? Params::months($this->months) : Params::weeks($this->weeks);
        $mode      = Params::mode($this->mode);

        $report = app(WipReviewReport::class)->build(
            (int) Auth::user()->company_id,
            $outletIds,
            $anchor,
            $count,
            $mode,
            $this->includeDraftPayroll,
            Employee::canViewPay(Auth::user()),
        );

        return view('livewire.reports.management.weekly-wip-review', [
            'report'       => $report,
            'outlets'      => $outlets,
            'weekOptions'  => WipReviewReport::WEEK_OPTIONS,
            'monthOptions' => WipReviewReport::MONTH_OPTIONS,
            'isLatest'     => $anchor->gte(Params::latest($mode)),
            'scopeLabel'   => $selected !== null
                ? ($outlets->firstWhere('id', $selected)?->name ?? 'One outlet')
                : 'All outlets',
            // Exactly the choices above, for the PDF of the same report.
            'pdfUrl'       => route('reports.weekly-wip-review.pdf', array_filter([
                'mode'   => $mode,
                'week'   => $monthly ? null : $anchor->toDateString(),
                'month'  => $monthly ? $anchor->format('Y-m') : null,
                'weeks'  => $monthly ? null : $count,
                'months' => $monthly ? $count : null,
                'outlet' => $selected,
                'drafts' => $monthly && $this->includeDraftPayroll ? 1 : null,
            ], fn ($v) => $v !== null)),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'WIP Review']);
    }
}
