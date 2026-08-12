<?php

namespace App\Livewire\Hr;

use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Section;
use App\Models\ServiceChargePeriod;
use App\Services\Hr\LatePenalties;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AttendanceRecords extends Component
{
    /** Longest period the grid/PDF will render (payroll cutoffs ≤ a month). */
    public const MAX_DAYS = 31;

    // Filters
    public string $search        = '';
    public string $outletFilter  = '';
    public string $sectionFilter = '';
    public string $employmentStatusFilter = ''; // '' all | status key | 'none'

    // Period: a calendar month by default, or a custom from–to range
    public string $periodMode = 'month'; // 'month' | 'range'
    public string $month      = '';      // Y-m
    public string $rangeFrom  = '';
    public string $rangeTo    = '';

    // Paint tool: the code applied when a day cell is clicked (null = eraser)
    public ?int $selectedCodeId = null;

    // Service charge panel: pool amount + per-day deduction percentages for
    // the current period; scLoadedKey tracks which period/outlet the inputs
    // were hydrated for so switching periods reloads the stored values.
    public bool   $showServiceCharge = false;
    public string $scAmount     = '';
    public string $scRetention  = '0';
    public string $scMcPercent  = '5';
    public string $scAbsPercent = '10';

    /** Named allocations that take points alongside staff: [['name','points']]. */
    public array $scFunds = [];

    /** employee_id => ['amount' => '', 'note' => ''] for this period only. */
    public array $scSpecial = [];

    /**
     * employee_id => true for staff taking NO share of this pool.
     *
     * Offered for resigned staff only. They are on this period because they
     * worked part of it and the default is that they earned their points; the
     * tick is the override for when that is not the agreement. Anyone still
     * employed is handled by the special deduction instead — that reduces one
     * person's pay, where this changes what a point is worth for everybody.
     */
    public array $scExcluded = [];
    public string $scLoadedKey  = '';

    // Manage-codes modal
    public bool   $showCodes     = false;
    public ?int   $editingCodeId = null;
    public string $c_code        = '';
    public string $c_label       = '';
    public string $c_color       = 'slate';
    public string $c_sort        = '';
    public bool   $c_is_active   = true;

    public function mount(): void
    {
        $user = Auth::user();

        AttendanceCode::seedDefaults($user->company_id);

        if ($this->outletFilter === '') {
            $activeOutletId = $user->activeOutletId();
            if ($activeOutletId) $this->outletFilter = (string) $activeOutletId;
        }

        $this->month     = now()->format('Y-m');
        $this->rangeFrom = now()->startOfMonth()->format('Y-m-d');
        $this->rangeTo   = now()->endOfMonth()->format('Y-m-d');

        // Default the paint tool to Present — the most common mark.
        $this->selectedCodeId = AttendanceCode::where('system_key', 'present')->value('id');
    }

    /** Outlet IDs this user may see — same scoping as the Employees module. */
    protected function accessibleOutletIds(): array
    {
        return Auth::user()->accessibleOutletIds();
    }

    /**
     * Resolve the current period to [from, to], clamped to MAX_DAYS so the
     * grid stays renderable regardless of what the range inputs hold.
     */
    public function period(): array
    {
        if ($this->periodMode === 'month') {
            try {
                $from = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
            } catch (\Throwable $e) {
                $from = now()->startOfMonth();
                $this->month = $from->format('Y-m');
            }
            return [$from->copy(), $from->copy()->endOfMonth()];
        }

        try {
            $from = Carbon::parse($this->rangeFrom)->startOfDay();
            $to   = Carbon::parse($this->rangeTo)->startOfDay();
        } catch (\Throwable $e) {
            $from = now()->startOfMonth();
            $to   = now()->endOfMonth();
        }
        if ($to->lt($from)) $to = $from->copy();
        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $to = $from->copy()->addDays(self::MAX_DAYS - 1);
        }
        $this->rangeFrom = $from->format('Y-m-d');
        $this->rangeTo   = $to->format('Y-m-d');

        return [$from, $to];
    }

    public function previousPeriod(): void
    {
        [$from, $to] = $this->period();
        if ($this->periodMode === 'month') {
            $this->month = $from->subMonthNoOverflow()->format('Y-m');
        } else {
            $days = (int) $from->diffInDays($to) + 1;
            $this->rangeFrom = $from->copy()->subDays($days)->format('Y-m-d');
            $this->rangeTo   = $to->copy()->subDays($days)->format('Y-m-d');
        }
    }

    public function nextPeriod(): void
    {
        [$from, $to] = $this->period();
        if ($this->periodMode === 'month') {
            $this->month = $from->addMonthNoOverflow()->format('Y-m');
        } else {
            $days = (int) $from->diffInDays($to) + 1;
            $this->rangeFrom = $from->copy()->addDays($days)->format('Y-m-d');
            $this->rangeTo   = $to->copy()->addDays($days)->format('Y-m-d');
        }
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

    // ── Service charge ─────────────────────────────────────────────────────

    /**
     * The service charge split is pay data — it exposes every employee's
     * service point entitlement and their RM share. Gated on hr.compensation
     * independently of hr.attendance, so a user can mark attendance without
     * seeing the money.
     */
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

    /**
     * Whether the service charge panel is available.
     *
     * Its OWN ability, not hr.compensation. Splitting a pool needs service
     * points and shares; it does not need anybody's basic salary, and the
     * permission that is titled "Attendance & Service Charge" should be the
     * one that grants it. The salary columns on this same screen stay on
     * canViewPay() — the two questions are asked separately from here on.
     */
    protected function canManageServiceCharge(): bool
    {
        return (bool) Auth::user()?->can('hr.attendance.service_charge');
    }

    /** Outlet key for the stored pool: the filtered outlet, or null for All. */
    protected function serviceChargeOutletId(): ?int
    {
        return $this->outletFilter !== '' ? (int) $this->outletFilter : null;
    }

    /**
     * Hydrate the panel inputs from the stored pool whenever the visible
     * period or outlet changes; returns the stored row (null if none yet).
     */
    protected function loadServiceCharge(): ?ServiceChargePeriod
    {
        [$from, $to] = $this->period();
        $key = ($this->outletFilter !== '' ? $this->outletFilter : 'all')
            . '|' . $from->format('Y-m-d') . '|' . $to->format('Y-m-d');

        $row = ServiceChargePeriod::where('outlet_id', $this->serviceChargeOutletId())
            ->whereDate('period_from', $from)
            ->whereDate('period_to', $to)
            ->first();

        if ($key !== $this->scLoadedKey) {
            $this->scAmount     = $row ? number_format((float) $row->amount, 2, '.', '') : '';
            $this->scMcPercent  = $row ? rtrim(rtrim(number_format((float) $row->mc_percent, 2, '.', ''), '0'), '.') : '5';
            $this->scAbsPercent = $row ? rtrim(rtrim(number_format((float) $row->abs_percent, 2, '.', ''), '0'), '.') : '10';
            $this->scRetention  = $row ? rtrim(rtrim(number_format((float) $row->retention_percent, 2, '.', ''), '0'), '.') : '0';

            $this->scFunds = $row
                ? array_map(fn ($f) => ['name' => $f['name'], 'points' => (string) $f['points']], $row->funds())
                : [];

            $this->scSpecial = collect($row?->special_deductions ?? [])
                ->mapWithKeys(fn ($d, $empId) => [(int) $empId => [
                    'amount' => (string) (float) ($d['amount'] ?? 0),
                    'note'   => (string) ($d['note'] ?? ''),
                ]])
                ->all();

            $this->scExcluded = collect($row?->excludedEmployeeIds() ?? [])
                ->mapWithKeys(fn ($id) => [$id => true])
                ->all();
            $this->scLoadedKey  = $key;
        }

        return $row;
    }

    /**
     * Distribution base for RM/point: the points of ALL active employees in
     * the outlet scope. Only the outlet filter narrows this — the section,
     * employment and search filters narrow the displayed rows but must not
     * change how much one point is worth.
     */
    /**
     * The ticked exclusions, narrowed to staff who may actually be excluded.
     *
     * Re-checked here rather than trusted from the form, because the tick
     * arrives from the browser and an exclusion applied to someone still
     * employed would quietly change what a point is worth for the whole
     * outlet.
     *
     * $savedIds are the exclusions already recorded against this pool, and
     * they survive the check: an exclusion is a decision about ONE closed
     * period, so someone who resigned in June and was later re-hired must not
     * have June silently reversed the next time that pool is saved. Removing
     * one is unticking it, not a change of employment status.
     *
     * @param  array<int, int>  $savedIds
     * @return array<int, int>
     */
    protected function excludedServicePointIds(array $savedIds = []): array
    {
        $ticked = array_map('intval', array_keys(array_filter($this->scExcluded)));

        if (! $ticked) {
            return [];
        }

        $allowed = Employee::whereIn('id', $ticked)
            ->whereIn('outlet_id', $this->accessibleOutletIds() ?: [0])
            ->where('employment_status', 'resigned')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_intersect($ticked, array_unique(array_merge($allowed, $savedIds))));
    }

    protected function serviceChargeTotalPoints(array $excludedIds = []): float
    {
        // employedDuring, NOT is_active: the rows being paid use that rule, so
        // a leaver whose points are in the payout but not in this base would
        // shrink the divisor, inflate RM/point and allocate more than the pool
        // holds. The two must be drawn from the same set of people — which is
        // also why the pool's exclusions come off here, not just off the rows.
        // Both ends: the end date is what keeps somebody hired AFTER the
        // period out of its divisor. Six August joiners were sitting in a
        // 1–25 July pool, and the day one of them is given points they would
        // dilute a period they did not work.
        [$periodFrom, $periodTo] = $this->period();

        // Scoped by who this POOL pays, not by who works at the outlet. A
        // person redirected to another outlet's pool takes no share here, so
        // their points must leave the divisor too — otherwise the pool
        // under-allocates and everybody else is quietly short-changed.
        $query = Employee::whereIn('outlet_id', $this->accessibleOutletIds() ?: [0])
            ->employedDuring($periodFrom->toDateString(), $periodTo->toDateString())
            ->forServiceChargeOutlet($this->outletFilter !== '' ? (int) $this->outletFilter : null);

        // Resolved by the caller and passed in, so the divisor and the rows
        // are computed from one list rather than two that could drift.
        return (float) ($excludedIds ? $query->whereNotIn('employees.id', $excludedIds) : $query)
            ->sum('service_points_entitlement');
    }

    public function addServiceChargeFund(): void
    {
        $this->scFunds[] = ['name' => '', 'points' => ''];
    }

    public function removeServiceChargeFund(int $index): void
    {
        unset($this->scFunds[$index]);
        // Re-index, or Livewire renders the array as an object and the
        // remaining rows lose their bindings.
        $this->scFunds = array_values($this->scFunds);
    }

    public function saveServiceCharge(): void
    {
        abort_unless($this->canManageServiceCharge(), 403);

        $this->validate([
            'scAmount'        => 'required|numeric|min:0|max:9999999999',
            'scRetention'     => 'required|numeric|min:0|max:100',
            'scMcPercent'     => 'required|numeric|min:0|max:100',
            'scAbsPercent'    => 'required|numeric|min:0|max:100',
            'scFunds'         => 'array|max:20',
            'scFunds.*.name'  => 'required|string|max:60',
            'scFunds.*.points' => 'required|numeric|min:0|max:9999',
            'scSpecial.*.amount' => 'nullable|numeric|min:0|max:9999999',
            'scSpecial.*.note'   => 'nullable|string|max:120',
            'scExcluded'         => 'array',
            'scExcluded.*'       => 'boolean',
        ], [
            'scFunds.*.name.required'   => 'Give every allocation a name.',
            'scFunds.*.points.required' => 'Give every allocation its points.',
        ], [
            'scAmount'     => 'service charge collected',
            'scRetention'  => 'retention %',
            'scMcPercent'  => 'MC deduction %',
            'scAbsPercent' => 'absent deduction %',
        ]);

        // Only rows with a real amount are stored, so clearing a field removes
        // the deduction rather than leaving a zero behind for someone to
        // wonder about later.
        $special = collect($this->scSpecial)
            ->filter(fn ($d) => (float) ($d['amount'] ?? 0) > 0)
            ->mapWithKeys(fn ($d, $empId) => [(string) $empId => [
                'amount' => round((float) $d['amount'], 2),
                'note'   => trim((string) ($d['note'] ?? '')) ?: null,
            ]])
            ->all();

        $funds = collect($this->scFunds)
            ->map(fn ($f) => ['name' => trim((string) $f['name']), 'points' => round((float) $f['points'], 2)])
            ->filter(fn ($f) => $f['name'] !== '' && $f['points'] > 0)
            ->values()
            ->all();

        [$from, $to] = $this->period();

        // Exclusions already on this pool survive the resigned-only check, so
        // re-saving a closed period cannot reverse a decision made about it.
        $existing = ServiceChargePeriod::where('outlet_id', $this->serviceChargeOutletId())
            ->whereDate('period_from', $from)
            ->whereDate('period_to', $to)
            ->first();

        ServiceChargePeriod::updateOrCreate(
            [
                'company_id'  => Auth::user()->company_id,
                'outlet_id'   => $this->serviceChargeOutletId(),
                'period_from' => $from->format('Y-m-d'),
                'period_to'   => $to->format('Y-m-d'),
            ],
            [
                'amount'             => round((float) $this->scAmount, 2),
                'retention_percent'  => round((float) $this->scRetention, 2),
                'mc_percent'         => round((float) $this->scMcPercent, 2),
                'abs_percent'        => round((float) $this->scAbsPercent, 2),
                'fund_allocations'   => $funds ?: null,
                'special_deductions' => $special ?: null,
                'excluded_employees' => $this->excludedServicePointIds(
                    $existing?->excludedEmployeeIds() ?? []
                ) ?: null,
            ]
        );

        session()->flash('success', 'Service charge saved for this period.');
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
            $keep = ['pay_type'];

            /*
             * Service points come back for anyone who may run the service
             * charge, because the panel is arithmetic OVER them: stripping
             * them would not have hidden a figure, it would have shown every
             * share as zero and looked like a broken pool. They are the one
             * entry on the sensitive list that is not a salary.
             */
            if ($this->canManageServiceCharge()) {
                $keep[] = 'service_points_entitlement';
            }

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
        if ($this->employmentStatusFilter === 'none') {
            $query->whereNull('employment_status');
        } elseif ($this->employmentStatusFilter === 'exclude_outsourcing') {
            $query->where(function ($q) {
                $q->whereNull('employment_status')->orWhere('employment_status', '!=', 'outsourcing');
            });
        } elseif ($this->employmentStatusFilter !== '') {
            $query->where('employment_status', $this->employmentStatusFilter);
        }

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

        $records = AttendanceRecord::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$from, $to])
            ->get();

        /*
         * TWO MAPS, from one read, and they must stay separate.
         *
         * $cellMap is the codes and only the codes — its shape is a contract
         * with ServiceChargePeriod::distribute(), which counts MC and absent
         * days out of it. An hours cell has no code, so it must not appear
         * there at all rather than appear as a null somebody later compares
         * against.
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
        $canManageServiceCharge = $this->canManageServiceCharge();

        $scRow = $this->loadServiceCharge();

        // Ticks that have not been saved yet are applied to the in-memory row
        // so the table and the RM/point above it move together — a divisor
        // that already dropped the points while the row was still being paid
        // would show an allocation the pool cannot cover. Never persisted:
        // saving goes through updateOrCreate, not this instance.
        $scExcludedIds = $this->excludedServicePointIds($scRow?->excludedEmployeeIds() ?? []);

        if ($scRow) {
            $scRow->excluded_employees = $scExcludedIds ?: null;
        }

        $serviceCharge = ($this->showServiceCharge && $canManageServiceCharge)
            ? ServiceChargePeriod::distribute(
                // The pool is the one this SCREEN is showing, which is not the
                // same as the one that happens to have been saved: an outlet
                // with no figure typed into it yet still has its own pool, and
                // redirected staff still belong to somebody else's.
                $scRow, $this->serviceChargeOutletId(), $employees, $codes, $cellMap,
                is_numeric($this->scMcPercent) ? (float) $this->scMcPercent : 5.0,
                is_numeric($this->scAbsPercent) ? (float) $this->scAbsPercent : 10.0,
                $this->serviceChargeTotalPoints($scExcludedIds),
                // Same outlet scope as the pool itself, so the deduction and
                // the pool it comes out of can never be drawn from different
                // sets of outlets.
                LatePenalties::forPeriod($companyId, $this->serviceChargeOutletId(), $from, $to),
            )
            : null;

        // The RM-per-minute the lateness column was priced at. Shown beside it
        // so the figure can be checked without opening Clock-In Settings.
        $lateRatePerMinute = ($this->showServiceCharge && $canManageServiceCharge)
            ? (float) \App\Models\ClockSetting::forCompany($companyId)->late_rate_per_minute
            : 0.0;

        return view('livewire.hr.attendance-records', compact(
            'lateRatePerMinute',
            'employees', 'monthlyEmployees', 'hourlyEmployees',
            'outlets', 'sections', 'canViewAll',
            'dates', 'from', 'to', 'codes', 'activeCodes', 'codesById', 'cellMap',
            'hoursMap', 'hourTotals',
            'presentCounts', 'absentCounts', 'serviceCharge', 'canViewPay', 'canManageServiceCharge',
        ))->layout('layouts.app', ['title' => 'Attendance Record']);
    }
}
