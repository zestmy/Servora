<?php

namespace App\Livewire\Hr;

use App\Models\Employee;
use App\Models\TimeOffRequest;
use App\Services\Hr\LeaveNotifier;
use App\Services\Hr\TimeOffBalance;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Time off taken against overtime the company has not paid.
 *
 * The balance shown here is APPROVED, UNPAID overtime. Once payroll approves a
 * run covering those hours they are paid in cash and disappear from it — an
 * hour is either paid or taken off, never both.
 */
class TimeOff extends Component
{
    use WithPagination;

    public string $outletFilter = '';
    public string $statusFilter = 'pending';
    public string $search       = '';

    public bool   $showApply  = false;
    public string $a_employee = '';
    public string $a_date     = '';
    public string $a_hours    = '';
    public string $a_reason   = '';

    public ?int   $decidingId   = null;
    public string $decisionNote = '';

    public function mount(): void
    {
        $this->a_date = now()->toDateString();

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

    public function openApply(?int $employeeId = null): void
    {
        $this->reset(['a_hours', 'a_reason']);
        $this->a_employee = (string) ($employeeId ?? '');
        $this->a_date = now()->toDateString();
        $this->resetErrorBag();
        $this->showApply = true;
    }

    public function apply(): void
    {
        $this->validate([
            'a_employee' => 'required|integer',
            'a_date'     => 'required|date',
            'a_hours'    => 'required|numeric|min:0.25|max:24',
            'a_reason'   => 'nullable|string|max:500',
        ], [], [
            'a_employee' => 'employee', 'a_date' => 'date', 'a_hours' => 'hours',
        ]);

        $employee = Employee::whereIn('outlet_id', $this->accessibleOutletIds() ?: [0])
            ->find((int) $this->a_employee);

        if (! $employee) {
            $this->addError('a_employee', 'You do not have access to that employee.');
            return;
        }

        $balance   = app(TimeOffBalance::class);
        $available = $balance->availableHours($employee);
        $hours     = round((float) $this->a_hours, 2);

        // Checked at APPLICATION, not just approval: someone planning an
        // afternoon off needs to know now, not after a manager looks.
        if ($hours > $available + 0.001) {
            $this->addError('a_hours', $employee->name . ' has '
                . rtrim(rtrim(number_format($available, 2), '0'), '.')
                . ' unpaid overtime hour(s) available.');
            return;
        }

        $created = TimeOffRequest::create([
            'company_id'  => $employee->company_id,
            'employee_id' => $employee->id,
            'off_date'    => $this->a_date,
            'hours'       => $hours,
            'reason'      => trim($this->a_reason) ?: null,
            'status'      => TimeOffRequest::PENDING,
            'applied_by'  => Auth::id(),
        ]);

        $told = app(LeaveNotifier::class)->submitted($created);

        $this->showApply = false;
        session()->flash('success', 'Time off applied for. It is waiting on approval.'
            . ($told ? " {$told} approver(s) notified." : ''));
    }

    public function decide(int $id, string $outcome): void
    {
        abort_unless($this->canApprove(), 403);

        $request = TimeOffRequest::with('employee')
            ->whereIn('employee_id', function ($q) {
                $q->select('id')->from('employees')->whereIn('outlet_id', $this->accessibleOutletIds() ?: [0]);
            })
            ->findOrFail($id);

        if (! $request->isPending()) {
            session()->flash('error', 'That request has already been decided.');
            return;
        }

        if ($outcome === TimeOffRequest::APPROVED) {
            // Allocation happens HERE, not at application: reserving hours
            // against claims that might then be paid out would leave the
            // ledger describing something that did not happen.
            try {
                app(TimeOffBalance::class)->allocate($request);
            } catch (\RuntimeException $e) {
                session()->flash('error', $e->getMessage());
                return;
            }
        }

        $request->update([
            'status'        => $outcome,
            'approved_by'   => Auth::id(),
            'approved_at'   => now(),
            'decision_note' => trim($this->decisionNote) ?: null,
        ]);

        app(LeaveNotifier::class)->decided($request->fresh(['employee', 'approver']));

        $this->decidingId   = null;
        $this->decisionNote = '';

        session()->flash('success', $outcome === TimeOffRequest::APPROVED
            ? 'Time off approved — the hours are now off the overtime balance.'
            : 'Time off rejected.');
    }

    public function cancel(int $id): void
    {
        $request = TimeOffRequest::whereIn('employee_id', function ($q) {
            $q->select('id')->from('employees')->whereIn('outlet_id', $this->accessibleOutletIds() ?: [0]);
        })->findOrFail($id);

        if ($request->status === TimeOffRequest::CANCELLED) {
            return;
        }

        // Approved hours were spent against real claims, so cancelling has to
        // hand them back — otherwise the overtime is neither paid nor taken.
        if ($request->status === TimeOffRequest::APPROVED) {
            app(TimeOffBalance::class)->release($request);
        }

        $request->update(['status' => TimeOffRequest::CANCELLED]);
        session()->flash('success', 'Time off cancelled — the hours are back on the overtime balance.');
    }

    public function render()
    {
        $user       = Auth::user();
        $accessible = $this->accessibleOutletIds();

        $employees = Employee::whereIn('outlet_id', $accessible ?: [0])
            ->where('is_active', true)
            ->when($this->outletFilter !== '', fn ($q) => $q->where('outlet_id', (int) $this->outletFilter))
            ->inListOrder()
            ->get();

        // company_id and daily_working_hours are SELECTED, not incidental: the
        // table converts each request's hours into days for that employee, and
        // a column subset that omitted them left the balance service holding a
        // null company and blew up in the view.
        $requests = TimeOffRequest::with([
                'employee:id,name,outlet_id,staff_id,company_id,daily_working_hours',
                'approver:id,name',
                'allocations.claim',
            ])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', fn ($q) => $q->whereIn('employee_id',
                $employees->filter(fn ($e) => stripos($e->name, $this->search) !== false)->pluck('id')))
            ->orderByDesc('off_date')
            ->paginate(15);

        $balance = app(TimeOffBalance::class);

        // Only staff with something to show — a table of zeroes for the whole
        // outlet buries the handful of people who actually have hours.
        $balances = $employees->take(80)->map(fn ($e) => [
            'employee'  => $e,
            'available' => $balance->availableHours($e),
            'day_hours' => $balance->workingDayHours($e),
        ])->filter(fn ($r) => $r['available'] > 0)->values();

        return view('livewire.hr.time-off', [
            'requests'   => $requests,
            'employees'  => $employees,
            'balances'   => $balances,
            'outlets'    => \App\Models\Outlet::where('company_id', $user->company_id)
                ->where('is_active', true)->whereIn('id', $accessible)->orderBy('name')->get(),
            'canApprove' => $this->canApprove(),
            'balanceService' => $balance,
            'pendingCount' => TimeOffRequest::whereIn('employee_id', $employees->pluck('id'))
                ->where('status', TimeOffRequest::PENDING)->count(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Time Off']);
    }
}
