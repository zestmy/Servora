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

    /**
     * Whether this user may delete a pool at all.
     *
     * Its own ability rather than riding on hr.attendance.service_charge, the
     * same shape the clock punches and overtime claims use: setting a pool and
     * destroying one are different acts, and the second removes the working
     * behind money that has already been handed out.
     */
    public function canDelete(): bool
    {
        return (bool) Auth::user()?->can('hr.attendance.service_charge.delete');
    }

    /**
     * Payroll runs paid from the selected pool, resolved through the service.
     *
     * Two different answers come out of this list. An APPROVED run refuses the
     * deletion outright: those payslips were paid from this pool and the pool
     * is the working behind them, so it is evidence rather than a draft.
     * DRAFT runs do not refuse — nothing has been paid — but they are named in
     * the confirmation, because a draft rebuilt after the pool is gone pays no
     * service charge at all, silently, and on an F&B payroll that is the
     * largest line after basic.
     */
    public function runsUsingSelected()
    {
        $user   = Auth::user();
        $period = $this->periods()->firstWhere('id', $this->periodId);

        if (! $period || ! $this->canDelete()) {
            return collect();
        }

        return $this->service()->runsUsing($period, $user->accessibleOutletIds());
    }

    public function delete(int $id): void
    {
        abort_unless($this->canDelete(), 403);

        $user = Auth::user();

        // Re-fetched through savedPeriods() rather than by id alone: that is
        // the query which scopes pools to this company and this user's
        // outlets, and an id arriving from a browser must not reach past it.
        $period = $this->periods()->firstWhere('id', $id);

        if (! $period) {
            session()->flash('error', 'That service charge pool is no longer available.');
            return;
        }

        $approved = $this->service()
            ->runsUsing($period, $user->accessibleOutletIds())
            ->where('status', \App\Models\PayrollRun::APPROVED);

        if ($approved->isNotEmpty()) {
            session()->flash('error',
                'This pool cannot be deleted — it was paid out by an approved payroll run ('
                . $approved->map(fn ($r) => $r->period_month?->format('M Y') ?? 'run #' . $r->id)
                    ->unique()->implode(', ')
                . '). Those payslips keep their own figures, but this is the working behind them.');
            return;
        }

        $label = $period->period_from->format('d M Y') . ' – ' . $period->period_to->format('d M Y')
            . ' · ' . ($period->outlet?->name ?? 'All outlets');

        $period->delete();

        // Back to whatever is left, so the page does not sit on a pool that
        // no longer exists and render an empty report.
        $this->periodId = $this->periods()->first()?->id;

        session()->flash('status', 'Deleted the service charge pool for ' . $label . '.');
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

        // Only asked when a delete is actually on offer — it walks every run
        // through forRun(), which is the honest way to answer it and not the
        // cheap one. See runsUsingSelected().
        $runsUsing = $this->canDelete() ? $this->runsUsingSelected() : collect();

        return view('livewire.reports.hr.service-charge-payout', [
            'periods'      => $periods,
            'period'       => $period,
            'distribution' => $distribution,
            'canDelete'    => $this->canDelete(),
            'runsUsing'    => $runsUsing,
            'approvedRuns' => $runsUsing->where('status', \App\Models\PayrollRun::APPROVED)->values(),
            'lateRate'     => (float) \App\Models\ClockSetting::forCompany($user->company_id)->late_rate_per_minute,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Service Charge Payout']);
    }
}
