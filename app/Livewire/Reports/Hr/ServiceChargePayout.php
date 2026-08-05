<?php

namespace App\Livewire\Reports\Hr;

use App\Services\Hr\ServiceChargeDistribution;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Service charge payout, as a report.
 *
 * The attendance grid computes the same split while you are marking the
 * month; this is where you go afterwards to answer "what did we pay out in
 * July". It lists the pools that have actually been SAVED rather than asking
 * for dates — a pool is keyed on its exact from/to, so a typed date that is a
 * day out silently finds nothing.
 */
class ServiceChargePayout extends Component
{
    /** id of the chosen service_charge_periods row. */
    public ?int $periodId = null;

    public function mount(): void
    {
        // Open on the most recent pool, so the page answers something on
        // arrival rather than asking a question first.
        $this->periodId = $this->periods()->first()?->id;
    }

    public function select(int $id): void
    {
        $this->periodId = $id;
    }

    private function service(): ServiceChargeDistribution
    {
        return app(ServiceChargeDistribution::class);
    }

    public function periods()
    {
        $user = Auth::user();

        return $this->service()->savedPeriods($user->company_id, $user->accessibleOutletIds());
    }

    public function render()
    {
        $user    = Auth::user();
        $periods = $this->periods();
        $period  = $periods->firstWhere('id', $this->periodId);

        $distribution = $period
            ? $this->service()->forPeriod(
                $user->company_id,
                $user->accessibleOutletIds(),
                Carbon::parse($period->period_from),
                Carbon::parse($period->period_to),
                $period->outlet_id,
            )
            : null;

        return view('livewire.reports.hr.service-charge-payout', [
            'periods'      => $periods,
            'period'       => $period,
            'distribution' => $distribution,
            'lateRate'     => (float) \App\Models\ClockSetting::forCompany($user->company_id)->late_rate_per_minute,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Service Charge Payout']);
    }
}
