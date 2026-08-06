<?php

namespace App\Livewire\Clock\Staff;

use App\Models\TimeOffRequest;
use App\Scopes\CompanyScope;
use App\Services\Hr\TimeOffBalance;

/**
 * The employee's own time off, taken against overtime not yet paid.
 *
 * Shows the balance in hours AND in days, because a working day here is 7.5,
 * 9 or 11 hours depending on the contract and "four hours" means a different
 * fraction of a day to different people.
 */
class TimeOff extends StaffComponent
{
    public string $f_date   = '';
    public string $f_hours  = '';
    public string $f_reason = '';

    public bool $showForm = false;

    public function mount(): void
    {
        $this->f_date = now()->toDateString();
    }

    public function openForm(): void
    {
        $this->reset(['f_hours', 'f_reason']);
        $this->f_date = now()->toDateString();
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function apply(): void
    {
        $staff = $this->staff();

        $this->validate([
            'f_date'   => 'required|date',
            'f_hours'  => 'required|numeric|min:0.25|max:24',
            'f_reason' => 'nullable|string|max:500',
        ], [], ['f_date' => 'date', 'f_hours' => 'hours']);

        $balance   = app(TimeOffBalance::class);
        $available = $balance->availableHours($staff);
        $hours     = round((float) $this->f_hours, 2);

        if ($hours > $available + 0.001) {
            $this->addError('f_hours', 'You have '
                . rtrim(rtrim(number_format($available, 2), '0'), '.')
                . ' unpaid overtime hour(s) available.');
            return;
        }

        TimeOffRequest::withoutGlobalScope(CompanyScope::class)->create([
            'company_id'  => $staff->company_id,
            'employee_id' => $staff->id,
            'off_date'    => $this->f_date,
            'hours'       => $hours,
            'reason'      => trim($this->f_reason) ?: null,
            'status'      => TimeOffRequest::PENDING,
            'applied_by'  => null,
        ]);

        $this->showForm = false;
        session()->flash('success', 'Applied. Your manager will see it for approval.');
    }

    public function cancel(int $id): void
    {
        $staff = $this->staff();

        $request = TimeOffRequest::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $staff->company_id)
            ->where('employee_id', $staff->id)
            ->find($id);

        if (! $request || $request->status === TimeOffRequest::CANCELLED) {
            return;
        }

        // An approved request already spent hours against real overtime
        // claims, so withdrawing it has to hand them back — otherwise those
        // hours are neither paid nor taken.
        if ($request->status === TimeOffRequest::APPROVED) {
            app(TimeOffBalance::class)->release($request);
        }

        $request->update(['status' => TimeOffRequest::CANCELLED]);
        session()->flash('success', 'Cancelled. The hours are back on your overtime.');
    }

    public function render()
    {
        $staff   = $this->staff();
        $balance = app(TimeOffBalance::class);

        $requests = TimeOffRequest::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $staff->company_id)
            ->where('employee_id', $staff->id)
            ->with('allocations.claim')
            ->orderByDesc('off_date')
            ->limit(30)
            ->get();

        $available = $balance->availableHours($staff);

        return view('livewire.clock.staff.time-off', [
            'requests'  => $requests,
            'available' => $available,
            'dayHours'  => $balance->workingDayHours($staff),
            'inDays'    => $balance->asDays($staff, $available),
            // The claims behind the balance, so "where does this come from"
            // is answered on the same screen rather than by asking someone.
            'claims'    => $balance->unpaidClaims($staff)->limit(20)->get(),
        ])->layout('layouts.clock-staff', $this->shell('My time off'));
    }
}
