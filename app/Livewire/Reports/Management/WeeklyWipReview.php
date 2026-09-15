<?php

namespace App\Livewire\Reports\Management;

use App\Services\Reports\WeeklyWipReview as WeeklyWipReviewReport;
use App\Traits\ScopesToActiveOutlet;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Weekly WIP meeting review — one week against the week before, as a scrolling
 * report or a full-screen slide deck (see the view).
 *
 * Opens on the last COMPLETE week: the meeting reviews the week just closed,
 * and a week still in progress would read as a collapse in sales every Monday.
 */
class WeeklyWipReview extends Component
{
    use ScopesToActiveOutlet;

    /** Monday of the reviewed week, Y-m-d. */
    public string $week = '';

    /** Weeks in the trend. Untyped: a select posts a string. */
    public $weeks = 8;

    public string $outletFilter = '';

    public function mount(): void
    {
        $this->week = $this->lastCompleteWeek()->toDateString();
    }

    public function updatedWeek(): void
    {
        $this->week = $this->normalise($this->week)->toDateString();
    }

    public function updatedWeeks(): void
    {
        if (! in_array((int) $this->weeks, WeeklyWipReviewReport::WEEK_OPTIONS, true)) {
            $this->weeks = 8;
        }
    }

    public function previousWeek(): void
    {
        $this->week = $this->normalise($this->week)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->week = $this->normalise($this->week)->addWeek()->toDateString();
    }

    /** Trend bars are clickable: review that week instead. */
    public function reviewWeek(string $start): void
    {
        $this->week = $this->normalise($start)->toDateString();
    }

    /** Any date → the Monday of its week, never later than the current week. */
    private function normalise(string $date): Carbon
    {
        try {
            $monday = Carbon::parse($date)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        } catch (\Throwable) {
            return $this->lastCompleteWeek();
        }

        $latest = now()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();

        return $monday->gt($latest) ? $latest : $monday;
    }

    private function lastCompleteWeek(): Carbon
    {
        return now()->startOfWeek(CarbonInterface::MONDAY)->subWeek()->startOfDay();
    }

    public function render()
    {
        $selected  = $this->selectedOutletId($this->outletFilter);
        $outletIds = $selected !== null ? [$selected] : $this->availableOutletIds();
        $outlets   = $this->filterableOutlets();
        $week      = $this->normalise($this->week);

        $report = app(WeeklyWipReviewReport::class)->build(
            (int) Auth::user()->company_id, $outletIds, $week, (int) $this->weeks,
        );

        return view('livewire.reports.management.weekly-wip-review', [
            'report'       => $report,
            'outlets'      => $outlets,
            'weekOptions'  => WeeklyWipReviewReport::WEEK_OPTIONS,
            'isLatestWeek' => $week->gte(now()->startOfWeek(CarbonInterface::MONDAY)->startOfDay()),
            'scopeLabel'   => $selected !== null
                ? ($outlets->firstWhere('id', $selected)?->name ?? 'One outlet')
                : 'All outlets',
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Weekly WIP Review']);
    }
}
