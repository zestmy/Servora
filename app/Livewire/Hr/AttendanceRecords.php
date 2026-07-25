<?php

namespace App\Livewire\Hr;

use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Section;
use App\Models\ServiceChargePeriod;
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
    public string $scMcPercent  = '5';
    public string $scAbsPercent = '10';
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
        $user = Auth::user();
        if ($user->canViewAllOutlets()) {
            return Outlet::where('company_id', $user->company_id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        return $user->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id)->all();
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

    /** Persist a drag-and-drop row order; ids outside the user's outlets are ignored. */
    public function reorderRows(array $orderedIds): void
    {
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

    /** Mark every empty cell in the visible grid as Present. */
    public function fillPresent(): void
    {
        $presentId = AttendanceCode::where('system_key', 'present')->value('id');
        if (! $presentId) return;

        [$from, $to] = $this->period();
        $employees = $this->employeesQuery()->get();
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

        session()->flash('success', count($rows) . ' day(s) marked as Present.');
    }

    /** Remove every mark in the visible grid (guarded by wire:confirm). */
    public function clearRange(): void
    {
        [$from, $to] = $this->period();
        $count = AttendanceRecord::whereIn('employee_id', $this->employeesQuery()->pluck('id'))
            ->whereBetween('work_date', [$from, $to])
            ->delete();

        session()->flash('success', $count . ' record(s) cleared.');
    }

    // ── Service charge ─────────────────────────────────────────────────────

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
            $this->scLoadedKey  = $key;
        }

        return $row;
    }

    public function saveServiceCharge(): void
    {
        $this->validate([
            'scAmount'     => 'required|numeric|min:0|max:9999999999',
            'scMcPercent'  => 'required|numeric|min:0|max:100',
            'scAbsPercent' => 'required|numeric|min:0|max:100',
        ], [], [
            'scAmount'     => 'service charge amount',
            'scMcPercent'  => 'MC deduction %',
            'scAbsPercent' => 'absent deduction %',
        ]);

        [$from, $to] = $this->period();
        ServiceChargePeriod::updateOrCreate(
            [
                'company_id'  => Auth::user()->company_id,
                'outlet_id'   => $this->serviceChargeOutletId(),
                'period_from' => $from->format('Y-m-d'),
                'period_to'   => $to->format('Y-m-d'),
            ],
            [
                'amount'      => round((float) $this->scAmount, 2),
                'mc_percent'  => round((float) $this->scMcPercent, 2),
                'abs_percent' => round((float) $this->scAbsPercent, 2),
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

        $query = Employee::with(['outlet', 'section'])
            ->whereIn('outlet_id', $accessible ?: [0])
            ->where('is_active', true)
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->orderBy('name');

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

    /**
     * Service charge distribution for the visible grid.
     *
     * The stored pool is split by Service Points entitlement: value per
     * point = pool / total points of eligible (points > 0) employees, gross
     * share = points x value per point. Each employee is then deducted
     * (MC days x mc%) + (absent days x abs%) of their own gross, capped at
     * 100%. MC days = cells marked with a code named MC or SL, or whose
     * label mentions "sick" (companies can rename/add codes); absent days
     * use the protected built-in Absent code.
     */
    protected function buildServiceCharge($employees, $codes, $cellMap, array $absentCounts): array
    {
        $row = $this->loadServiceCharge();

        $mcCodeIds = $codes->filter(fn ($c) => in_array(strtoupper(trim($c->code)), ['MC', 'SL'], true)
                || stripos($c->label, 'sick') !== false)
            ->pluck('id')->all();

        $mcCounts = [];
        foreach ($cellMap as $key => $codeId) {
            if (in_array($codeId, $mcCodeIds, true)) {
                $empId = (int) strtok($key, ':');
                $mcCounts[$empId] = ($mcCounts[$empId] ?? 0) + 1;
            }
        }

        $totalPoints = $employees->sum(fn ($e) => max(0, (float) $e->service_points_entitlement));
        $perPoint    = ($row && $totalPoints > 0) ? (float) $row->amount / $totalPoints : 0.0;
        $mcPct       = $row ? (float) $row->mc_percent : (float) $this->scMcPercent;
        $absPct      = $row ? (float) $row->abs_percent : (float) $this->scAbsPercent;

        $rows   = [];
        $totals = ['gross' => 0.0, 'deduction' => 0.0, 'net' => 0.0];
        foreach ($employees as $emp) {
            $points  = max(0, (float) $emp->service_points_entitlement);
            $mcDays  = $mcCounts[$emp->id] ?? 0;
            $absDays = $absentCounts[$emp->id] ?? 0;
            $dedPct  = min(100.0, $mcDays * $mcPct + $absDays * $absPct);
            $gross   = $points * $perPoint;
            $dedAmt  = $gross * $dedPct / 100;

            $rows[] = [
                'employee' => $emp,
                'points'   => $points,
                'mcDays'   => $mcDays,
                'absDays'  => $absDays,
                'dedPct'   => $dedPct,
                'gross'    => $gross,
                'dedAmt'   => $dedAmt,
                'net'      => $gross - $dedAmt,
            ];
            $totals['gross']     += $gross;
            $totals['deduction'] += $dedAmt;
            $totals['net']       += $gross - $dedAmt;
        }

        return [
            'row'         => $row,
            'rows'        => $rows,
            'totals'      => $totals,
            'totalPoints' => $totalPoints,
            'perPoint'    => $perPoint,
            'mcPct'       => $mcPct,
            'absPct'      => $absPct,
        ];
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

        // "empId:Y-m-d" → attendance_code_id
        $cellMap = AttendanceRecord::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$from, $to])
            ->get()
            ->mapWithKeys(fn ($r) => [$r->employee_id . ':' . $r->work_date->format('Y-m-d') => $r->attendance_code_id]);

        $presentId = $codes->firstWhere('system_key', 'present')?->id;
        $absentId  = $codes->firstWhere('system_key', 'absent')?->id;

        $presentCounts = [];
        $absentCounts  = [];
        foreach ($cellMap as $key => $codeId) {
            $empId = (int) strtok($key, ':');
            if ($codeId === $presentId) $presentCounts[$empId] = ($presentCounts[$empId] ?? 0) + 1;
            if ($codeId === $absentId)  $absentCounts[$empId]  = ($absentCounts[$empId] ?? 0) + 1;
        }

        $serviceCharge = $this->showServiceCharge
            ? $this->buildServiceCharge($employees, $codes, $cellMap, $absentCounts)
            : null;

        return view('livewire.hr.attendance-records', compact(
            'employees', 'outlets', 'sections', 'canViewAll',
            'dates', 'from', 'to', 'codes', 'activeCodes', 'codesById', 'cellMap',
            'presentCounts', 'absentCounts', 'serviceCharge',
        ))->layout('layouts.app', ['title' => 'Attendance Record']);
    }
}
