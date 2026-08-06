<?php

namespace App\Livewire\Hr;

use App\Models\Employee;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Services\Hr\LeaveBalance;
use App\Services\Hr\LeaveNotifier;
use App\Services\Hr\ReplacementHolidays;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Leave: the requests waiting on a decision, and what each employee has left.
 *
 * One screen rather than two, because "should I approve this" and "how much
 * do they have left" are the same question asked half a second apart.
 */
class Leave extends Component
{
    use WithPagination;

    public string $year         = '';
    public string $outletFilter = '';
    public string $statusFilter = 'pending';
    public string $search       = '';

    // Apply / record leave
    public bool   $showApply    = false;
    public string $a_employee   = '';
    public string $a_type       = '';
    /** Which public holiday a replacement day is being taken against. */
    public string $a_holiday    = '';
    public string $a_start      = '';
    public string $a_end        = '';
    public string $a_days       = '';
    public bool   $a_half       = false;
    public string $a_half_period = 'am';
    public string $a_reason     = '';

    // Grant entitlement
    public bool   $showGrant    = false;
    public string $g_employee   = '';
    public string $g_type       = '';
    public string $g_entitled   = '';
    public string $g_carried    = '0';
    public string $g_adjustment = '0';
    public string $g_notes      = '';

    // Decide
    public ?int   $decidingId   = null;
    public string $decisionNote = '';

    public function mount(): void
    {
        $this->year = (string) now()->year;
        LeaveType::seedDefaults(Auth::user()->company_id);

        if ($outlet = Auth::user()?->activeOutletId()) {
            $this->outletFilter = (string) $outlet;
        }
    }

    protected function accessibleOutletIds(): array
    {
        return Auth::user()->accessibleOutletIds();
    }

    public function canApprove(): bool
    {
        return (bool) Auth::user()?->can('hr.leave.approve');
    }

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingOutletFilter(): void { $this->resetPage(); }

    // ── Applying ─────────────────────────────────────────────────────────

    public function openApply(): void
    {
        $this->reset(['a_employee', 'a_type', 'a_holiday', 'a_start', 'a_end', 'a_days', 'a_reason']);
        $this->a_half = false;
        $this->a_start = now()->toDateString();
        $this->a_end   = now()->toDateString();
        $this->resetErrorBag();
        $this->showApply = true;
    }

    /**
     * The unspent replacement credits the chosen employee could book, or an
     * empty collection when the form is not on a replacement type.
     *
     * @return \Illuminate\Support\Collection<int, array{holiday: \App\Models\PublicHoliday, expires_on: ?Carbon, status: string, request: ?LeaveRequest, taken_on: ?Carbon}>
     */
    public function replacementCredits()
    {
        $type = $this->a_type !== '' ? LeaveType::find((int) $this->a_type) : null;

        if (! $type?->is_replacement_holiday || $this->a_employee === '') {
            return collect();
        }

        $employee = Employee::whereIn('outlet_id', $this->accessibleOutletIds() ?: [0])
            ->find((int) $this->a_employee);

        return $employee ? app(ReplacementHolidays::class)->claimable($employee) : collect();
    }

    /** True while the apply form is on the replacement type. */
    public function applyingReplacement(): bool
    {
        return $this->a_type !== ''
            && (bool) LeaveType::find((int) $this->a_type)?->is_replacement_holiday;
    }

    /*
     * A replacement is one whole day against one named holiday, so choosing a
     * type or a person invalidates whichever credit was picked and pins the
     * day count. Letting the count stay editable would invite "3 days" against
     * a single credit, which the balance would then have to refuse.
     */
    public function updatedAType(): void
    {
        $this->a_holiday = '';

        if ($this->applyingReplacement()) {
            $this->a_half = false;
            $this->a_days = '1';
            $this->a_end  = $this->a_start;
        }
    }

    public function updatedAEmployee(): void
    {
        $this->a_holiday = '';
    }

    /**
     * Suggest the day count from the dates, without taking the decision away.
     *
     * Calendar days are only ever a starting point — rest days, half days and
     * public holidays all change the answer, and the form lets it be edited.
     */
    public function updatedAEnd(): void   { $this->suggestDays(); }
    public function updatedAStart(): void { $this->suggestDays(); }
    public function updatedAHalf(): void  { $this->suggestDays(); }

    private function suggestDays(): void
    {
        // A replacement day is one credit, one day — the dates do not get a
        // vote, and the end date follows the start.
        if ($this->applyingReplacement()) {
            $this->a_days = '1';
            $this->a_end  = $this->a_start;
            return;
        }

        if ($this->a_half) {
            $this->a_days = '0.5';
            $this->a_end  = $this->a_start;
            return;
        }

        try {
            $from = Carbon::parse($this->a_start)->startOfDay();
            $to   = Carbon::parse($this->a_end)->startOfDay();
        } catch (\Throwable $e) {
            return;
        }

        if ($to->lt($from)) {
            return;
        }

        $this->a_days = (string) ($from->diffInDays($to) + 1);
    }

    public function apply(): void
    {
        $this->validate([
            'a_employee' => 'required|integer',
            'a_type'     => 'required|integer',
            'a_start'    => 'required|date',
            'a_end'      => 'required|date',
            'a_days'     => 'required|numeric|min:0.5|max:400',
            'a_reason'   => 'nullable|string|max:500',
        ], [], [
            'a_employee' => 'employee', 'a_type' => 'leave type',
            'a_start' => 'start date', 'a_end' => 'end date', 'a_days' => 'days',
        ]);

        $employee = Employee::whereIn('outlet_id', $this->accessibleOutletIds() ?: [0])
            ->find((int) $this->a_employee);

        if (! $employee) {
            $this->addError('a_employee', 'You do not have access to that employee.');
            return;
        }

        $type = LeaveType::find((int) $this->a_type);

        if (! $type) {
            $this->addError('a_type', 'That leave type no longer exists.');
            return;
        }

        // The whole point of the is_claimable flag — an entitlement paid out
        // with salary is recorded and reported, never booked.
        if ($blocked = $type->blockedReason()) {
            $this->addError('a_type', $blocked);
            return;
        }

        if (Carbon::parse($this->a_end)->lt(Carbon::parse($this->a_start))) {
            $this->addError('a_end', 'The end date cannot be before the start date.');
            return;
        }

        if ($this->a_half && ! $type->allows_half_day) {
            $this->addError('a_days', 'Half days are not allowed for ' . $type->name . '.');
            return;
        }

        $days    = round((float) $this->a_days, 1);
        $year    = (int) Carbon::parse($this->a_start)->format('Y');
        $holiday = null;

        if ($type->is_replacement_holiday) {
            /*
             * A replacement draws on ONE NAMED HOLIDAY, not on a yearly
             * figure, so the ordinary balance check is skipped rather than
             * added to. It would ask the wrong question: a credit earned in
             * December and taken in January belongs to December's balance, and
             * a year-based check would refuse it on 2 January every time.
             */
            $holiday = $this->resolveReplacementCredit($employee, $days);

            if (! $holiday) {
                return;
            }
        } else {
            // Checked at APPLICATION, not approval: someone who books a flight
            // on the strength of a request that was never going to fit has a
            // much worse day than someone told "no" straight away.
            $remaining = app(LeaveBalance::class)->remainingFor($employee, $type, $year);

            if ($days > $remaining + 0.001) {
                $this->addError('a_days', $employee->name . ' has '
                    . rtrim(rtrim(number_format($remaining, 1), '0'), '.')
                    . ' day(s) of ' . $type->name . ' left for ' . $year . '.');
                return;
            }
        }

        // A type that needs no approval is granted on the spot — otherwise it
        // would sit in a queue nobody is expected to look at.
        $autoApprove = ! $type->requires_approval;

        $created = LeaveRequest::create([
            'company_id'        => $employee->company_id,
            'employee_id'       => $employee->id,
            'leave_type_id'     => $type->id,
            'public_holiday_id' => $holiday?->id,
            'start_date'      => $this->a_start,
            'end_date'        => $this->a_half ? $this->a_start : $this->a_end,
            'days'            => $days,
            'is_half_day'     => $this->a_half,
            'half_day_period' => $this->a_half ? $this->a_half_period : null,
            'reason'          => trim($this->a_reason) ?: null,
            'status'          => $autoApprove ? LeaveRequest::APPROVED : LeaveRequest::PENDING,
            'applied_by'      => Auth::id(),
            'approved_by'     => $autoApprove ? Auth::id() : null,
            'approved_at'     => $autoApprove ? now() : null,
        ]);

        // Notify AFTER the request exists — a notification for something that
        // failed to save would be worse than none.
        $told = $autoApprove ? 0 : app(LeaveNotifier::class)->submitted($created);

        $this->showApply = false;
        session()->flash('success', $autoApprove
            ? 'Leave recorded — ' . $type->name . ' needs no approval.'
            : 'Leave applied for. It is now waiting on approval.'
              . ($told ? " {$told} approver(s) notified." : ' Nobody is set up to be notified — check the reporting line or leave approvers.'));
    }

    /**
     * The holiday this replacement day is being taken against, or null with an
     * error already on the form.
     *
     * Re-checked here rather than trusted from the picker because the list was
     * rendered some seconds ago: the employee may have booked the same credit
     * on their phone in between, and two days off for one holiday is exactly
     * the error nobody notices until the payroll run.
     */
    private function resolveReplacementCredit(Employee $employee, float $days): ?PublicHoliday
    {
        if ($this->a_holiday === '') {
            $this->addError('a_holiday', 'Pick which public holiday this day is replacing.');
            return null;
        }

        if (abs($days - 1.0) > 0.001) {
            $this->addError('a_days', 'A replacement day is one whole day — one public holiday, one day back.');
            return null;
        }

        $service = app(ReplacementHolidays::class);
        $credit  = $service->claimable($employee)
            ->firstWhere('holiday.id', (int) $this->a_holiday);

        if (! $credit) {
            $this->addError('a_holiday', $employee->name
                . ' has no unused replacement day for that holiday — it may have just been booked.');
            return null;
        }

        $reason = $service->reasonCannotTake(
            $credit['holiday'], Carbon::parse($this->a_start), $employee
        );

        if ($reason) {
            $this->addError('a_start', $reason);
            return null;
        }

        return $credit['holiday'];
    }

    // ── Deciding ─────────────────────────────────────────────────────────

    public function decide(int $id, string $outcome): void
    {
        abort_unless($this->canApprove(), 403);

        $request = LeaveRequest::whereIn('employee_id', function ($q) {
            $q->select('id')->from('employees')->whereIn('outlet_id', $this->accessibleOutletIds() ?: [0]);
        })->findOrFail($id);

        if (! $request->isPending()) {
            session()->flash('error', 'That request has already been decided.');
            return;
        }

        // Re-checked at approval as well as application: other requests may
        // have been approved in between and eaten the balance.
        //
        // A replacement is exempt. It holds ONE named credit from the moment
        // it is applied for — a pending request already counts as spending it,
        // so nothing else can have taken it — and the year-based check below
        // would ask about the wrong year for a December credit taken in
        // January.
        if ($outcome === LeaveRequest::APPROVED && ! $request->leaveType->is_replacement_holiday) {
            $remaining = app(LeaveBalance::class)->remainingFor(
                $request->employee, $request->leaveType, $request->balanceYear()
            );

            // The request's own days are inside `remaining` already, since a
            // pending request counts against the balance — so it fits if
            // removing it would leave nothing negative.
            if ($remaining < -0.001) {
                session()->flash('error', 'This no longer fits ' . $request->employee->name
                    . "'s balance — other leave has been approved since it was applied for.");
                return;
            }
        }

        $request->update([
            'status'        => $outcome,
            'approved_by'   => Auth::id(),
            'approved_at'   => now(),
            'decision_note' => trim($this->decisionNote) ?: null,
        ]);

        app(LeaveNotifier::class)->decided($request->fresh(['employee', 'leaveType', 'approver']));

        $this->decidingId   = null;
        $this->decisionNote = '';

        session()->flash('success', $outcome === LeaveRequest::APPROVED ? 'Leave approved.' : 'Leave rejected.');
    }

    public function cancel(int $id): void
    {
        $request = LeaveRequest::whereIn('employee_id', function ($q) {
            $q->select('id')->from('employees')->whereIn('outlet_id', $this->accessibleOutletIds() ?: [0]);
        })->findOrFail($id);

        if ($request->status === LeaveRequest::CANCELLED) {
            return;
        }

        $request->update(['status' => LeaveRequest::CANCELLED]);
        session()->flash('success', 'Leave cancelled — the days are back on the balance.');
    }

    // ── Entitlement ──────────────────────────────────────────────────────

    public function openGrant(?int $employeeId = null): void
    {
        abort_unless($this->canApprove(), 403);

        $this->reset(['g_employee', 'g_type', 'g_entitled', 'g_notes']);
        $this->g_carried = '0';
        $this->g_adjustment = '0';
        $this->g_employee = (string) ($employeeId ?? '');
        $this->resetErrorBag();
        $this->showGrant = true;
    }

    public function grant(): void
    {
        abort_unless($this->canApprove(), 403);

        $this->validate([
            'g_employee'   => 'required|integer',
            'g_type'       => 'required|integer',
            'g_entitled'   => 'required|numeric|min:0|max:400',
            'g_carried'    => 'required|numeric|min:0|max:400',
            'g_adjustment' => 'required|numeric|min:-400|max:400',
            'g_notes'      => 'nullable|string|max:255',
        ], [], [
            'g_employee' => 'employee', 'g_type' => 'leave type', 'g_entitled' => 'entitled days',
        ]);

        $employee = Employee::whereIn('outlet_id', $this->accessibleOutletIds() ?: [0])
            ->find((int) $this->g_employee);

        if (! $employee) {
            $this->addError('g_employee', 'You do not have access to that employee.');
            return;
        }

        // Refused rather than saved-and-ignored. The replacement balance is
        // read off the holiday register, so a figure entered here would have
        // no effect at all — and a number on screen that changes nothing is
        // worse than being told no.
        if (LeaveType::find((int) $this->g_type)?->is_replacement_holiday) {
            $this->addError('g_type', 'Replacement days are earned from the public holiday register, not granted — add or edit the holiday there instead.');
            return;
        }

        LeaveEntitlement::updateOrCreate(
            [
                'employee_id'   => $employee->id,
                'leave_type_id' => (int) $this->g_type,
                'year'          => (int) $this->year,
            ],
            [
                'company_id'      => $employee->company_id,
                'entitled_days'   => round((float) $this->g_entitled, 1),
                'carried_days'    => round((float) $this->g_carried, 1),
                'adjustment_days' => round((float) $this->g_adjustment, 1),
                'notes'           => trim($this->g_notes) ?: null,
                'granted_by'      => Auth::id(),
            ]
        );

        $this->showGrant = false;
        session()->flash('success', 'Entitlement saved for ' . $employee->name . '.');
    }

    public function render()
    {
        $user       = Auth::user();
        $accessible = $this->accessibleOutletIds();
        $year       = (int) ($this->year ?: now()->year);

        $employees = Employee::whereIn('outlet_id', $accessible ?: [0])
            ->where('is_active', true)
            ->inListOrder()
            ->get();

        $requests = LeaveRequest::with(['employee:id,name,outlet_id,staff_id', 'leaveType', 'publicHoliday:id,name,date', 'approver:id,name'])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->outletFilter !== '', fn ($q) => $q->whereIn('employee_id',
                $employees->where('outlet_id', (int) $this->outletFilter)->pluck('id')))
            ->when($this->search !== '', fn ($q) => $q->whereIn('employee_id',
                $employees->filter(fn ($e) => stripos($e->name, $this->search) !== false)->pluck('id')))
            ->orderByDesc('start_date')
            ->paginate(15);

        // Balances for the employees actually on screen — the whole company
        // would be a query per person for people nobody is looking at.
        $balanceService = app(LeaveBalance::class);
        $shown = $this->outletFilter !== ''
            ? $employees->where('outlet_id', (int) $this->outletFilter)
            : $employees;

        $balances = $shown->take(50)->mapWithKeys(
            fn ($e) => [$e->id => $balanceService->forEmployee($e, $year)]
        );

        return view('livewire.hr.leave', [
            'requests'   => $requests,
            'employees'  => $employees,
            'balances'   => $balances,
            'types'      => LeaveType::active()->ordered()->get(),
            'credits'    => $this->replacementCredits(),
            'isReplacement' => $this->applyingReplacement(),
            'outlets'    => \App\Models\Outlet::where('company_id', $user->company_id)
                ->where('is_active', true)->whereIn('id', $accessible)->orderBy('name')->get(),
            'canApprove' => $this->canApprove(),
            'pendingCount' => LeaveRequest::whereIn('employee_id', $employees->pluck('id'))
                ->where('status', LeaveRequest::PENDING)->count(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Leave']);
    }
}
