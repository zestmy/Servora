<?php

namespace App\Livewire\Hr;

use App\Livewire\Hr\Concerns\PicksAttendancePeriod;
use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Section;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AttendanceRecords extends Component
{
    use PicksAttendancePeriod;

    /** Longest period the grid/PDF will render (payroll cutoffs ≤ a month). */
    public const MAX_DAYS = 31;

    // Filters
    public string $search        = '';
    public string $outletFilter  = '';
    public string $sectionFilter = '';
    public string $employmentStatusFilter = ''; // '' all | status key | 'none'
    public string $employmentTypeFilter   = ''; // '' all | Employee::EMPLOYMENT_TYPE_FILTERS key

    // Paint tool: the code applied when a day cell is clicked (null = eraser)
    public ?int $selectedCodeId = null;

    // Manage-codes modal
    public bool   $showCodes     = false;
    public ?int   $editingCodeId = null;
    public string $c_code        = '';
    public string $c_label       = '';
    public string $c_color       = 'slate';
    public string $c_sort        = '';
    public bool   $c_is_active   = true;
    public bool   $c_working_day = false;

    public function mount(): void
    {
        $user = Auth::user();

        AttendanceCode::seedDefaults($user->company_id);

        if ($this->outletFilter === '') {
            $activeOutletId = $user->activeOutletId();
            if ($activeOutletId) $this->outletFilter = (string) $activeOutletId;
        }

        $this->initPeriod();

        // Default the paint tool to Present — the most common mark.
        $this->selectedCodeId = AttendanceCode::where('system_key', 'present')->value('id');
    }

    /** Outlet IDs this user may see — same scoping as the Employees module. */
    protected function accessibleOutletIds(): array
    {
        return Auth::user()->accessibleOutletIds();
    }

    public function selectCode(?int $codeId): void
    {
        $this->selectedCodeId = $codeId;
    }

    /** Apply the selected code to (employee, date); eraser clears the cell. */
    public function setCell(int $employeeId, string $date): void
    {
        // Marking attendance feeds straight into payroll, so editing the grid is a
        // separate ability from reading it. Re-checked here because a Livewire action
        // is its own request, not a re-entry through the route.
        abort_unless(Auth::user()?->canDo('hr.attendance.record'), 403);

        $employee = Employee::find($employeeId);
        if (! $employee || ! in_array((int) $employee->outlet_id, $this->accessibleOutletIds(), true)) {
            return;
        }

        [$from, $to] = $this->period();
        try {
            $day = Carbon::parse($date)->startOfDay();
        } catch (\Throwable $e) {
            return;
        }
        if ($day->lt($from) || $day->gt($to)) return;

        if ($this->selectedCodeId === null) {
            AttendanceRecord::where('employee_id', $employee->id)
                ->whereDate('work_date', $day)
                ->delete();
            return;
        }

        $code = AttendanceCode::find($this->selectedCodeId);
        if (! $code) return;

        AttendanceRecord::updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => $day->format('Y-m-d')],
            [
                'company_id'         => Auth::user()->company_id,
                'outlet_id'          => $employee->outlet_id,
                'attendance_code_id' => $code->id,
            ]
        );
    }

    /**
     * One cell on the hourly table, typed rather than tapped.
     *
     * ONE INPUT, TWO KINDS OF ANSWER, and that is deliberate. A part-timer's
     * month is mostly numbers — "4.5", "6", "8" — with the occasional MC or
     * annual leave among them. Splitting those into a number field and a code
     * palette would mean moving between two controls to fill one row, on the
     * screen whose entry speed is the whole reason it exists. So the cell takes
     * whatever is typed and works out which it was:
     *
     *   "4.5" → hours worked
     *   "MC"  → the MC code, matched case-insensitively against the palette
     *   ""    → the cell is cleared
     *
     * Anything else is ignored rather than guessed at. Silently storing zero
     * hours for a typo would be a paid day quietly turned into an unpaid one.
     */
    public function setHourlyCell(int $employeeId, string $date, string $raw): void
    {
        // Marking attendance feeds straight into payroll, so editing the grid is a
        // separate ability from reading it. Re-checked here because a Livewire action
        // is its own request, not a re-entry through the route.
        abort_unless(Auth::user()?->canDo('hr.attendance.record'), 403);

        $employee = Employee::find($employeeId);
        if (! $employee || ! in_array((int) $employee->outlet_id, $this->accessibleOutletIds(), true)) {
            return;
        }

        [$from, $to] = $this->period();
        try {
            $day = Carbon::parse($date)->startOfDay();
        } catch (\Throwable $e) {
            return;
        }
        if ($day->lt($from) || $day->gt($to)) return;

        $value = trim($raw);
        $keys  = ['employee_id' => $employee->id, 'work_date' => $day->format('Y-m-d')];

        if ($value === '') {
            AttendanceRecord::where('employee_id', $employee->id)
                ->whereDate('work_date', $day)
                ->delete();

            return;
        }

        $base = [
            'company_id' => Auth::user()->company_id,
            'outlet_id'  => $employee->outlet_id,
        ];

        // A number, in any of the ways somebody writes one: 4, 4.5, 4,5.
        $numeric = str_replace(',', '.', $value);

        if (is_numeric($numeric)) {
            $hours = round((float) $numeric, 2);

            // Zero is not "worked no hours", it is a mistake or a clearing —
            // and 24 is the ceiling because a day is. Both drop the row rather
            // than storing a figure payroll would then multiply by a rate.
            if ($hours <= 0 || $hours > 24) {
                AttendanceRecord::where('employee_id', $employee->id)
                    ->whereDate('work_date', $day)
                    ->delete();

                return;
            }

            AttendanceRecord::updateOrCreate($keys, $base + [
                'hours'              => $hours,
                'attendance_code_id' => null,
            ]);

            return;
        }

        // Not a number, so it has to be a code somebody knows the letters of.
        $code = AttendanceCode::whereRaw('UPPER(TRIM(code)) = ?', [mb_strtoupper($value)])->first();

        if (! $code) {
            return;
        }

        AttendanceRecord::updateOrCreate($keys, $base + [
            'attendance_code_id' => $code->id,
            'hours'              => null,
        ]);
    }

    /** Persist a drag-and-drop row order; ids outside the user's outlets are ignored. */
    public function reorderRows(array $orderedIds): void
    {
        // Marking attendance feeds straight into payroll, so editing the grid is a
        // separate ability from reading it. Re-checked here because a Livewire action
        // is its own request, not a re-entry through the route.
        abort_unless(Auth::user()?->canDo('hr.attendance.record'), 403);

        $allowed = Employee::whereIn('id', $orderedIds)
            ->whereIn('outlet_id', $this->accessibleOutletIds() ?: [0])
            ->pluck('id')
            ->flip();

        $index = 0;
        foreach ($orderedIds as $id) {
            if (! isset($allowed[(int) $id])) continue;
            Employee::where('id', (int) $id)->update(['sort_order' => $index++]);
        }
    }

    /**
     * Mark every empty cell in the SALARIED grid as Present.
     *
     * Hourly staff are excluded, and that is the difference between a
     * convenience and a payroll incident. "Present" says nothing about how long
     * somebody was here, so stamping it across a part-timer's month would fill
     * every blank day with a code — and a coded day is a day with no hours,
     * which is a day that pays nothing. The button would have quietly written
     * off the days nobody had got round to entering yet.
     */
    public function fillPresent(): void
    {
        // Marking attendance feeds straight into payroll, so editing the grid is a
        // separate ability from reading it. Re-checked here because a Livewire action
        // is its own request, not a re-entry through the route.
        abort_unless(Auth::user()?->canDo('hr.attendance.record'), 403);

        $presentId = AttendanceCode::where('system_key', 'present')->value('id');
        if (! $presentId) return;

        [$from, $to] = $this->period();
        $employees = $this->employeesQuery()->get()
            ->reject(fn ($e) => $e->pay_type === 'hourly');
        $companyId = Auth::user()->company_id;

        $existing = AttendanceRecord::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$from, $to])
            ->get()
            ->keyBy(fn ($r) => $r->employee_id . ':' . $r->work_date->format('Y-m-d'));

        $now  = now();
        $rows = [];
        foreach ($employees as $emp) {
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $key = $emp->id . ':' . $d->format('Y-m-d');
                if (isset($existing[$key])) continue;
                $rows[] = [
                    'company_id'         => $companyId,
                    'outlet_id'          => $emp->outlet_id,
                    'employee_id'        => $emp->id,
                    'work_date'          => $d->format('Y-m-d'),
                    'attendance_code_id' => $presentId,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            AttendanceRecord::insert($chunk);
        }

        session()->flash('success', count($rows) . ' day(s) marked as Present on the salaried table.');
    }

    /** Remove every mark in the visible grid (guarded by wire:confirm). */
    public function clearRange(): void
    {
        // Marking attendance feeds straight into payroll, so editing the grid is a
        // separate ability from reading it. Re-checked here because a Livewire action
        // is its own request, not a re-entry through the route.
        abort_unless(Auth::user()?->canDo('hr.attendance.record'), 403);

        [$from, $to] = $this->period();
        $count = AttendanceRecord::whereIn('employee_id', $this->employeesQuery()->pluck('id'))
            ->whereBetween('work_date', [$from, $to])
            ->delete();

        session()->flash('success', $count . ' record(s) cleared.');
    }

    // ── Abilities ──────────────────────────────────────────────────────────

    /**
     * May this person change the grid?
     *
     * The cells ARE the control here, so there is no button to hide — without this the
     * grid renders as static values, the paint palette and the fill/clear actions go, and
     * the code editor is read-only. Every write still re-checks on its own; this only
     * stops the screen offering an edit it would then refuse.
     */
    public function canRecord(): bool
    {
        return Auth::user()?->canDo('hr.attendance.record') ?? false;
    }

    protected function canViewPay(): bool
    {
        return Employee::canViewPay(Auth::user());
    }

    // ── Manage codes ───────────────────────────────────────────────────────

    public function openCodeCreate(): void
    {
        $this->resetCodeForm();
        $this->c_sort = (string) ((int) AttendanceCode::max('sort_order') + 10);
        $this->showCodes = true;
    }

    public function openCodeEdit(int $id): void
    {
        $code = AttendanceCode::findOrFail($id);
        $this->editingCodeId = $code->id;
        $this->c_code        = $code->code;
        $this->c_label       = $code->label;
        $this->c_color       = $code->color;
        $this->c_sort        = (string) $code->sort_order;
        $this->c_is_active   = $code->is_active;
        $this->c_working_day = (bool) $code->counts_as_working_day;
    }

    public function saveCode(): void
    {
        // Marking attendance feeds straight into payroll, so editing the grid is a
        // separate ability from reading it. Re-checked here because a Livewire action
        // is its own request, not a re-entry through the route.
        abort_unless(Auth::user()?->canDo('hr.attendance.record'), 403);

        $this->validate([
            'c_code'  => 'required|string|max:10',
            'c_label' => 'required|string|max:100',
            'c_color' => 'required|in:' . implode(',', array_keys(AttendanceCode::COLORS)),
            'c_sort'  => 'nullable|integer|min:0',
        ], [], ['c_code' => 'code', 'c_label' => 'label', 'c_color' => 'color', 'c_sort' => 'sort order']);

        $duplicate = AttendanceCode::whereRaw('LOWER(code) = ?', [mb_strtolower(trim($this->c_code))])
            ->when($this->editingCodeId, fn ($q) => $q->where('id', '!=', $this->editingCodeId))
            ->exists();
        if ($duplicate) {
            $this->addError('c_code', 'This code already exists.');
            return;
        }

        $data = [
            'company_id' => Auth::user()->company_id,
            'code'       => trim($this->c_code),
            'label'      => trim($this->c_label),
            'color'      => $this->c_color,
            'sort_order' => (int) ($this->c_sort ?: 0),
            'is_active'  => $this->c_is_active,
            // Read by allowances paid per working day (a meal allowance).
            'counts_as_working_day' => $this->c_working_day,
        ];

        if ($this->editingCodeId) {
            $code = AttendanceCode::findOrFail($this->editingCodeId);
            // System codes keep their code text stable — the module and the
            // bulk-fill button depend on them; label and color stay editable.
            if ($code->system_key) {
                unset($data['code'], $data['is_active']);
            }
            $code->update($data);
        } else {
            AttendanceCode::create($data);
        }

        $this->resetCodeForm();
    }

    public function deleteCode(int $id): void
    {
        // Marking attendance feeds straight into payroll, so editing the grid is a
        // separate ability from reading it. Re-checked here because a Livewire action
        // is its own request, not a re-entry through the route.
        abort_unless(Auth::user()?->canDo('hr.attendance.record'), 403);

        $code = AttendanceCode::findOrFail($id);
        if ($code->system_key) {
            session()->flash('error', 'Built-in codes cannot be deleted.');
            return;
        }
        if (AttendanceRecord::where('attendance_code_id', $code->id)->exists()) {
            session()->flash('error', 'This code is used by attendance records — deactivate it instead.');
            return;
        }
        if ($this->selectedCodeId === $code->id) {
            $this->selectedCodeId = AttendanceCode::where('system_key', 'present')->value('id');
        }
        $code->delete();
    }

    public function toggleCodeActive(int $id): void
    {
        $code = AttendanceCode::findOrFail($id);
        if ($code->system_key) return;
        $code->update(['is_active' => ! $code->is_active]);
        if (! $code->is_active && $this->selectedCodeId === $code->id) {
            $this->selectedCodeId = AttendanceCode::where('system_key', 'present')->value('id');
        }
    }

    protected function resetCodeForm(): void
    {
        $this->editingCodeId = null;
        $this->c_code      = '';
        $this->c_label     = '';
        $this->c_color     = 'slate';
        $this->c_sort      = '';
        $this->c_is_active = true;
        $this->c_working_day = false;
    }

    // ── Query & render ─────────────────────────────────────────────────────

    protected function employeesQuery()
    {
        $accessible = $this->accessibleOutletIds();

        // Active staff plus anyone who resigned during (or after the start of)
        // the visible period — their final days still need marking.
        [$periodFrom, $periodTo] = $this->period();

        $query = Employee::with(['outlet', 'section'])
            ->whereIn('outlet_id', $accessible ?: [0])
            ->employedDuring($periodFrom->toDateString(), $periodTo->toDateString())
            ->inListOrder();

        /*
         * Never load pay columns for users without hr.compensation — except
         * pay_type, which decides WHICH TABLE a person's row belongs on.
         *
         * It is on the sensitive list because it qualifies a salary figure, and
         * beside an amount that is right. On its own it is a rostering fact:
         * this person's day is counted in hours. An HR clerk marking attendance
         * has to know that to mark it at all, and withholding it did not
         * protect anything — it just put a part-timer on the salaried table
         * with nowhere to enter their hours. The rate itself stays hidden; the
         * columns that would show it are gated on $canViewPay in the view.
         */
        if (! $this->canViewPay()) {
            // The service charge, which needs service points, is on its
            // own page now (ServiceCharge) and loads its own staff.
            $keep = ['pay_type'];

            $query->select(array_values(array_diff(
                \Illuminate\Support\Facades\Schema::getColumnListing('employees'),
                array_diff(Employee::SENSITIVE_PAY_ATTRIBUTES, $keep)
            )));
        }

        if ($this->search !== '') {
            $s = '%' . $this->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('staff_id', 'like', $s)
                  ->orWhere('designation', 'like', $s);
            });
        }
        if ($this->outletFilter !== '') {
            $query->where('outlet_id', (int) $this->outletFilter);
        }
        if ($this->sectionFilter !== '') {
            $query->where('section_id', (int) $this->sectionFilter);
        }
        Employee::applyEmploymentFilters($query, $this->employmentStatusFilter, $this->employmentTypeFilter);

        return $query;
    }

    public function render()
    {
        $user      = Auth::user();
        $companyId = $user->company_id;

        $accessible = $this->accessibleOutletIds();
        $canViewAll = $user->canViewAllOutlets();

        $outlets = Outlet::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', $accessible)
            ->orderBy('name')
            ->get();

        $sections = Section::active()->ordered()->get();

        [$from, $to] = $this->period();
        $dates = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $dates[] = $d->copy();
        }

        $employees = $this->employeesQuery()->get();

        // All codes (inactive included) so cells keep rendering codes that
        // were deactivated after use; the palette shows active ones only.
        $codes       = AttendanceCode::orderBy('sort_order')->orderBy('code')->get();
        $activeCodes = $codes->where('is_active', true);
        $codesById   = $codes->keyBy('id');

        $records = AttendanceRecord::whereIn('employee_id', $employees->pluck('id')->all())
            ->whereBetween('work_date', [$from, $to])
            ->get();

        /*
         * TWO MAPS, from one read, and they must stay separate.
         *
         * $cellMap is the codes and only the codes; an hours cell has no code,
         * so it must not appear there at all rather than appear as a null
         * somebody later compares against. (The Service Charge page builds
         * the same map for its own staff — see ServiceCharge::render().)
         */
        // "empId:Y-m-d" → attendance_code_id
        $cellMap = $records
            ->filter(fn ($r) => $r->attendance_code_id !== null)
            ->mapWithKeys(fn ($r) => [$r->employee_id . ':' . $r->work_date->format('Y-m-d') => $r->attendance_code_id]);

        // "empId:Y-m-d" → hours worked
        $hoursMap = $records
            ->filter(fn ($r) => $r->hours !== null)
            ->mapWithKeys(fn ($r) => [$r->employee_id . ':' . $r->work_date->format('Y-m-d') => (float) $r->hours]);

        $hourTotals = [];
        foreach ($hoursMap as $key => $hours) {
            $empId = (int) strtok($key, ':');
            $hourTotals[$empId] = round(($hourTotals[$empId] ?? 0) + $hours, 2);
        }

        /*
         * Two rosters, because they are two different documents that happen to
         * cover the same month. A monthly row asks "were you here"; an hourly
         * row asks "how long for". Interleaving them put a column of ticks and
         * a column of numbers under one heading, where the totals underneath
         * could not mean anything for both.
         */
        $hourlyEmployees  = $employees->filter(fn ($e) => $e->pay_type === 'hourly')->values();
        $monthlyEmployees = $employees->reject(fn ($e) => $e->pay_type === 'hourly')->values();

        $presentId = $codes->firstWhere('system_key', 'present')?->id;
        $absentId  = $codes->firstWhere('system_key', 'absent')?->id;

        $presentCounts = [];
        $absentCounts  = [];
        foreach ($cellMap as $key => $codeId) {
            $empId = (int) strtok($key, ':');
            if ($codeId === $presentId) $presentCounts[$empId] = ($presentCounts[$empId] ?? 0) + 1;
            if ($codeId === $absentId)  $absentCounts[$empId]  = ($absentCounts[$empId] ?? 0) + 1;
        }

        $canViewPay = $this->canViewPay();
        return view('livewire.hr.attendance-records', compact(
            'employees', 'monthlyEmployees', 'hourlyEmployees',
            'outlets', 'sections', 'canViewAll',
            'dates', 'from', 'to', 'codes', 'activeCodes', 'codesById', 'cellMap',
            'hoursMap', 'hourTotals',
            'presentCounts', 'absentCounts', 'canViewPay',
        ))->layout('layouts.app', ['title' => 'Attendance Record']);
    }
}
