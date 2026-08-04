<?php

namespace App\Livewire\Hr;

use App\Models\Employee;
use App\Models\EmployeeFaceDescriptor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
 */
class FaceEnrolment extends Component
{
    /** Below this a face is too poorly enrolled to be worth keeping. */
    private const MIN_CAPTURES = 3;

    /** Above this, more captures stop helping and just cost comparison time. */
    private const MAX_CAPTURES = 8;

    public string $search = '';

    #[Locked]
    public ?int $employeeId = null;

    #[Locked]
    public string $errorMessage = '';

    public function mount(?int $employee = null): void
    {
        if ($employee) {
            $this->select($employee);
        }
    }

    public function select(int $id): void
    {
        $this->employeeId   = $this->findEmployee($id)?->id;
        $this->errorMessage = '';
    }

    public function clearSelection(): void
    {
        $this->employeeId   = null;
        $this->errorMessage = '';
    }

    /**
     * @param  array  $payload  {descriptor: number[], photo?: string}
     */
    public function enrol(array $payload): void
    {
        abort_unless(Auth::user()->can('hr.clock.manage'), 403);

        $this->errorMessage = '';

        $employee = $this->employee();

        if (! $employee) {
            return;
        }

        $descriptor = $payload['descriptor'] ?? null;

        if (! EmployeeFaceDescriptor::isValidDescriptor($descriptor)) {
            $this->errorMessage = 'That capture could not be read. Try again with the face filling more of the frame.';

            return;
        }

        if ($this->captures()->count() >= self::MAX_CAPTURES) {
            $this->errorMessage = 'That is already ' . self::MAX_CAPTURES . ' captures — plenty. Delete one first if you want to replace it.';

            return;
        }

        EmployeeFaceDescriptor::create([
            'company_id'    => $employee->company_id,
            'employee_id'   => $employee->id,
            'descriptor'    => array_map('floatval', $descriptor),
            'model_version' => EmployeeFaceDescriptor::MODEL_VERSION,
            'photo_path'    => $this->storePhoto($employee, $payload['photo'] ?? null),
            'enrolled_by'   => Auth::id(),
        ]);

        session()->flash('success', 'Capture saved.');
    }

    public function deleteCapture(int $id): void
    {
        abort_unless(Auth::user()->can('hr.clock.manage'), 403);

        $capture = $this->captures()->firstWhere('id', $id);

        if (! $capture) {
            return;
        }

        if ($capture->photo_path) {
            Storage::disk('local')->delete($capture->photo_path);
        }

        $capture->delete();

        session()->flash('success', 'Capture deleted.');
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

    /** The reference photo, on the private disk — never public. */
    private function storePhoto(Employee $employee, ?string $dataUrl): ?string
    {
        if (! is_string($dataUrl) || ! preg_match('#^data:image/(jpeg|jpg|png);base64,#', $dataUrl, $m)) {
            return null;
        }

        if (strlen($dataUrl) > 2_500_000) {
            return null;
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binary === false || $binary === '' || ! @getimagesizefromstring($binary)) {
            return null;
        }

        $path = sprintf(
            'face-enrolments/%d/%d/%s.%s',
            $employee->company_id, $employee->id, Str::uuid(), $m[1] === 'png' ? 'png' : 'jpg'
        );

        return Storage::disk('local')->put($path, $binary) ? $path : null;
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
            'minCaptures' => self::MIN_CAPTURES,
            'maxCaptures' => self::MAX_CAPTURES,
        ])->layout('layouts.app', ['title' => 'Face Enrolment']);
    }
}
