<?php

namespace App\Livewire\Hr;

use App\Http\Controllers\Hr\FaceEnrolmentController;
use App\Models\Employee;
use App\Models\EmployeeFaceDescriptor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Enrols the faces the clock-in app matches against.
 *
 * Done by a manager, on a manager's screen, with the employee standing
 * there — not self-service on the employee's own phone. Whoever enrols a
 * face decides who that face belongs to, and letting somebody register
 * their own would hand them the ability to register a friend's.
 *
 * Several captures per person is the point, not an accident. One head-on
 * shot stops working the week somebody grows a beard or clocks in under a
 * different light; a handful taken at slightly different angles keeps the
 * daily punch working without having to loosen the threshold for everyone.
 *
 * READ-ONLY BY DESIGN. This component renders the screen and nothing else —
 * choosing a name is a link, saving a capture is a fetch to
 * FaceEnrolmentController, and deleting one is a form post. Enrolment gates
 * the whole feature, so none of it may depend on Livewire's JavaScript
 * having started; it was failing silently on exactly that.
 */
class FaceEnrolment extends Component
{
    public string $search = '';

    #[Locked]
    public ?int $employeeId = null;

    /**
     * Who is being enrolled comes from the URL, not from a Livewire action.
     *
     * Picking a name used to be a wire:click, which meant it did nothing at
     * all on a device where Livewire had not started — the list responded to
     * taps by staying exactly as it was, with no way to tell that from a slow
     * network. A plain link works with no JavaScript whatsoever, and it makes
     * the choice bookmarkable into the bargain.
     */
    public function mount(?int $employee = null): void
    {
        $employee ??= request()->integer('employee') ?: null;

        if ($employee) {
            $this->employeeId = $this->findEmployee($employee)?->id;
        }
    }

    public function employee(): ?Employee
    {
        return $this->employeeId ? $this->findEmployee($this->employeeId) : null;
    }

    private function findEmployee(int $id): ?Employee
    {
        return Employee::whereIn('outlet_id', Auth::user()->accessibleOutletIds() ?: [0])
            ->find($id);
    }

    public function captures()
    {
        $employee = $this->employee();

        if (! $employee) {
            return collect();
        }

        return EmployeeFaceDescriptor::where('employee_id', $employee->id)
            ->orderBy('id')
            ->get();
    }

    public function render()
    {
        $employees = Employee::whereIn('outlet_id', Auth::user()->accessibleOutletIds() ?: [0])
            ->where('is_active', true)
            ->when($this->search !== '', fn ($q) => $q
                ->where(fn ($w) => $w->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('staff_id', 'like', '%' . $this->search . '%')))
            ->inListOrder()
            ->limit(50)
            ->get();

        // How many faces each of them has, so the list shows who is still
        // missing one — the question this screen exists to answer.
        $counts = EmployeeFaceDescriptor::whereIn('employee_id', $employees->pluck('id'))
            ->selectRaw('employee_id, COUNT(*) as total')
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        return view('livewire.hr.face-enrolment', [
            'employees'   => $employees,
            'counts'      => $counts,
            'selected'    => $this->employee(),
            'captures'    => $this->captures(),
            'minCaptures' => FaceEnrolmentController::MIN_CAPTURES,
            'maxCaptures' => FaceEnrolmentController::MAX_CAPTURES,
        ])->layout('layouts.app', ['title' => 'Face Enrolment']);
    }
}
