<?php

namespace App\Livewire\Clock\Staff;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Scopes\CompanyScope;
use App\Services\Hr\LeaveBalance;
use App\Services\Hr\LeaveNotifier;
use App\Services\Hr\ReplacementHolidays;
use Carbon\Carbon;

/**
 * The employee's own leave: what they have left, and applying for it.
 *
 * Self-service on the staff app rather than the manager screens, because the
 * people who take leave mostly do not have a manager login — they have a PIN
 * and a phone. Everything here is scoped to the signed-in employee by hand:
 * there is no authenticated web user, so CompanyScope cannot be relied on and
 * getting it wrong would show somebody else's leave.
 */
class Leave extends StaffComponent
{
    public string $f_type   = '';
    /** Which public holiday a replacement day is being taken against. */
    public string $f_holiday = '';
    public string $f_start  = '';
    public string $f_end    = '';
    public string $f_days   = '';
    public bool   $f_half   = false;
    public string $f_half_period = 'am';
    public string $f_reason = '';

    public bool $showForm = false;

    public function mount(): void
    {
        $this->f_start = now()->toDateString();
        $this->f_end   = now()->toDateString();
    }

    public function openForm(): void
    {
        $this->reset(['f_type', 'f_holiday', 'f_days', 'f_reason']);
        $this->f_half  = false;
        $this->f_start = now()->toDateString();
        $this->f_end   = now()->toDateString();
        $this->resetErrorBag();
        $this->showForm = true;
    }

    /** The chosen type, scoped to the employee's own company by hand. */
    private function chosenType(): ?LeaveType
    {
        if ($this->f_type === '') {
            return null;
        }

        return LeaveType::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->staff()->company_id)
            ->find((int) $this->f_type);
    }

    /** True while the form is on the replacement public holiday type. */
    public function applyingReplacement(): bool
    {
        return (bool) $this->chosenType()?->is_replacement_holiday;
    }

    /*
     * A replacement is one whole day against one named holiday, so switching
     * type drops whichever credit was picked and pins the day count. Leaving
     * "3" in the box against a single credit only earns a refusal on submit.
     */
    public function updatedFType(): void
    {
        $this->f_holiday = '';

        if ($this->applyingReplacement()) {
            $this->f_half = false;
            $this->f_days = '1';
            $this->f_end  = $this->f_start;
        }
    }

    public function updatedFEnd(): void  { $this->suggestDays(); }
    public function updatedFHalf(): void { $this->suggestDays(); }

    /*
     * The two fields drive EACH OTHER, because people fill this in both ways.
     *
     * Change the dates and the day count follows; type a day count and the
     * end date moves to match. Typing "2" and watching To sit on the same day
     * as From is the version that gets submitted wrong.
     *
     * No loop: Livewire fires updated hooks on USER input only, so assigning
     * these properties from inside the handlers does not re-enter them.
     */
    public function updatedFStart(): void { $this->suggestEnd(); }
    public function updatedFDays(): void  { $this->suggestEnd(); }

    /** Move the end date to match the day count, from the start date. */
    private function suggestEnd(): void
    {
        // One credit, one day — the two fields stop driving each other.
        if ($this->applyingReplacement()) {
            $this->f_days = '1';
            $this->f_end  = $this->f_start;
            return;
        }

        if ($this->f_half) {
            $this->f_end  = $this->f_start;
            $this->f_days = '0.5';
            return;
        }

        $days = (float) $this->f_days;

        if ($days <= 0) {
            return;
        }

        try {
            $from = Carbon::parse($this->f_start)->startOfDay();
        } catch (\Throwable $e) {
            return;
        }

        // Whole days only: 2 days from Monday ends Tuesday, so the span is
        // days − 1. A half day beyond a whole one still ends that same last
        // day, which is why this rounds up rather than truncating.
        $this->f_end = $from->copy()->addDays((int) ceil($days) - 1)->toDateString();
    }

    /** A starting point from the dates — rest days still have to be taken off. */
    private function suggestDays(): void
    {
        if ($this->applyingReplacement()) {
            $this->f_days = '1';
            $this->f_end  = $this->f_start;
            return;
        }

        if ($this->f_half) {
            $this->f_days = '0.5';
            $this->f_end  = $this->f_start;
            return;
        }

        try {
            $from = Carbon::parse($this->f_start)->startOfDay();
            $to   = Carbon::parse($this->f_end)->startOfDay();
        } catch (\Throwable $e) {
            return;
        }

        if ($to->lt($from)) {
            return;
        }

        $this->f_days = (string) ($from->diffInDays($to) + 1);
    }

    public function apply(): void
    {
        $staff = $this->staff();

        $this->validate([
            'f_type'   => 'required|integer',
            'f_start'  => 'required|date',
            'f_end'    => 'required|date',
            'f_days'   => 'required|numeric|min:0.5|max:400',
            'f_reason' => 'nullable|string|max:500',
        ], [], [
            'f_type' => 'leave type', 'f_start' => 'start date',
            'f_end' => 'end date', 'f_days' => 'days',
        ]);

        // Scoped to the employee's OWN company by hand — there is no
        // authenticated user here for the global scope to work from.
        $type = LeaveType::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $staff->company_id)
            ->find((int) $this->f_type);

        if (! $type) {
            $this->addError('f_type', 'That leave type is not available.');
            return;
        }

        // The type's own reasons, then the ones that depend on WHO is asking —
        // the probation gate on annual leave. Checked here as well as shown on
        // the balance screen, because a balance is a display and this is the
        // thing that actually refuses.
        $blocked = $type->blockedReason()
            ?? app(\App\Services\Hr\LeaveRules::class)->blockedReason($this->staff(), $type);

        if ($blocked) {
            $this->addError('f_type', $blocked);
            return;
        }

        if (Carbon::parse($this->f_end)->lt(Carbon::parse($this->f_start))) {
            $this->addError('f_end', 'The end date cannot be before the start date.');
            return;
        }

        if ($this->f_half && ! $type->allows_half_day) {
            $this->addError('f_days', 'Half days are not allowed for ' . $type->name . '.');
            return;
        }

        $days    = round((float) $this->f_days, 1);
        $year    = (int) Carbon::parse($this->f_start)->format('Y');
        $holiday = null;

        if ($type->is_replacement_holiday) {
            /*
             * A replacement draws on ONE NAMED HOLIDAY, so the yearly balance
             * check is skipped rather than added to — it would ask the wrong
             * question. A day earned in December and taken in January belongs
             * to December's credits, and a year-based check would refuse it
             * every 2 January.
             */
            $holiday = $this->resolveReplacementCredit($days);

            if (! $holiday) {
                return;
            }
        } else {
            $remaining = app(LeaveBalance::class)->remainingFor($staff, $type, $year);

            if ($days > $remaining + 0.001) {
                $this->addError('f_days', 'You have '
                    . rtrim(rtrim(number_format($remaining, 1), '0'), '.')
                    . ' day(s) of ' . $type->name . ' left for ' . $year . '.');
                return;
            }
        }

        $autoApprove = ! $type->requires_approval;

        $created = LeaveRequest::withoutGlobalScope(CompanyScope::class)->create([
            'company_id'        => $staff->company_id,
            'employee_id'       => $staff->id,
            'leave_type_id'     => $type->id,
            'public_holiday_id' => $holiday?->id,
            'start_date'      => $this->f_start,
            'end_date'        => $this->f_half ? $this->f_start : $this->f_end,
            'days'            => $days,
            'is_half_day'     => $this->f_half,
            'half_day_period' => $this->f_half ? $this->f_half_period : null,
            'reason'          => trim($this->f_reason) ?: null,
            'status'          => $autoApprove ? LeaveRequest::APPROVED : LeaveRequest::PENDING,
            // No web user to record — the staff app signs in with a PIN.
            'applied_by'      => null,
            'approved_at'     => $autoApprove ? now() : null,
        ]);

        if (! $autoApprove) {
            app(LeaveNotifier::class)->submitted($created);
        }

        $this->showForm = false;
        session()->flash('success', $autoApprove
            ? 'Recorded. ' . $type->name . ' does not need approval.'
            : 'Applied. Your manager has been notified.');
    }

    /**
     * The holiday this replacement day is being taken against, or null with an
     * error already on the form.
     *
     * Re-resolved from the service rather than trusted from the picker: the
     * list was rendered some seconds ago and a manager may have booked the
     * same credit from the office in between.
     */
    private function resolveReplacementCredit(float $days): ?PublicHoliday
    {
        $staff = $this->staff();

        if ($this->f_holiday === '') {
            $this->addError('f_holiday', 'Choose which public holiday you are taking this day for.');
            return null;
        }

        if (abs($days - 1.0) > 0.001) {
            $this->addError('f_days', 'A replacement day is one whole day — one public holiday, one day back.');
            return null;
        }

        $service = app(ReplacementHolidays::class);
        $credit  = $service->claimable($staff)->firstWhere('holiday.id', (int) $this->f_holiday);

        if (! $credit) {
            $this->addError('f_holiday', 'You have no unused replacement day for that holiday — it may have just been booked for you.');
            return null;
        }

        $reason = $service->reasonCannotTake($credit['holiday'], Carbon::parse($this->f_start), $staff);

        if ($reason) {
            $this->addError('f_start', $reason);
            return null;
        }

        return $credit['holiday'];
    }

    /**
     * Withdraw your own request.
     *
     * Allowed while pending or approved: plans change, and the alternative is
     * asking a manager to do it, which is the sort of small errand this screen
     * exists to remove.
     */
    public function cancel(int $id): void
    {
        $staff = $this->staff();

        $request = LeaveRequest::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $staff->company_id)
            ->where('employee_id', $staff->id)
            ->find($id);

        if (! $request || $request->status === LeaveRequest::CANCELLED) {
            return;
        }

        $request->update(['status' => LeaveRequest::CANCELLED]);
        session()->flash('success', 'Cancelled. The days are back on your balance.');
    }

    public function render()
    {
        $staff = $this->staff();
        $year  = (int) now()->year;

        $balances = app(LeaveBalance::class)->forEmployee($staff, $year);

        $requests = LeaveRequest::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $staff->company_id)
            ->where('employee_id', $staff->id)
            ->with(['leaveType', 'publicHoliday:id,name,date'])
            ->orderByDesc('start_date')
            ->limit(30)
            ->get();

        $replacements = app(ReplacementHolidays::class);

        return view('livewire.clock.staff.leave', [
            'balances' => $balances,
            'requests' => $requests,
            'year'     => $year,
            // The whole year's credits, not just the unused ones: "which
            // holidays am I still owed for" and "which did I already take"
            // are the same list, and staff read it as a statement.
            'credits'  => $replacements->creditsFor($staff, $year),
            'claimable'     => $replacements->claimable($staff),
            'isReplacement' => $this->applyingReplacement(),
        ])->layout('layouts.clock-staff', $this->shell('My leave'));
    }
}
