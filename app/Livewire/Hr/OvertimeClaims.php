<?php

namespace App\Livewire\Hr;

use App\Models\Section;
use App\Models\Employee;
use App\Models\OvertimeClaim;
use App\Models\OvertimeClaimApprover;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class OvertimeClaims extends Component
{
    use WithPagination;

    // Filters
    public string $statusFilter     = '';
    public string $dateFrom         = '';
    public string $dateTo           = '';
    public string $employeeFilter   = '';
    public string $sectionFilter = '';
    public string $sortField        = 'claim_date';
    public string $sortDirection    = 'desc';
    public int    $perPage          = 25;

    // Form modal
    public bool   $showModal         = false;
    public ?int   $editingId         = null;
    public ?int   $employee_id       = null;
    public string $claim_date        = '';
    public string $ot_time_start     = '';
    public string $ot_time_end       = '';
    public string $total_ot_hours    = '';
    public string $ot_type           = 'normal_day';
    public string $reason            = '';

    // Reject modal
    public bool   $showRejectModal   = false;
    public ?int   $rejectingId       = null;
    public string $rejected_reason   = '';

    // Bulk reject modal
    public bool   $showBulkRejectModal   = false;
    public string $bulk_rejected_reason  = '';

    // Employee modals (list lives on /hr/employees now)
    public bool   $showEmployeeModal = false;
    public ?int   $editingEmployeeId = null;
    public string $emp_name          = '';
    public string $emp_designation   = '';
    public ?int   $emp_section_id    = null;

    // Bulk selection
    public array  $selected = [];

    // PDF print modal
    public bool   $showPdfModal  = false;
    public string $pdfFrom       = '';
    public string $pdfTo         = '';
    public string $pdfEmployeeId = '';

    // Summary PDF modal
    public bool   $showSummaryModal = false;
    public string $summaryMonth     = '';
    public string $summaryYear      = '';

    protected function rules(): array
    {
        return [
            'employee_id'    => 'required|exists:employees,id',
            'claim_date'     => 'required|date',
            'ot_time_start'  => 'required|date_format:H:i',
            'ot_time_end'    => 'required|date_format:H:i',
            'total_ot_hours' => 'required|numeric|min:0.25|max:24',
            'ot_type'        => 'required|in:normal_day,public_holiday,rest_day',
            'reason'         => 'required|string|max:500',
        ];
    }

    protected function messages(): array
    {
        return [
            'employee_id.required'   => 'Please select an employee.',
            'ot_time_end.date_format' => 'Please enter a valid end time.',
            'total_ot_hours.min'     => 'Minimum OT is 0.25 hours (15 minutes).',
            'reason.required'        => 'Please provide a reason for the overtime.',
        ];
    }

    public function updatedOtTimeStart(): void { $this->calcHours(); }
    public function updatedOtTimeEnd(): void   { $this->calcHours(); }

    public function updatedStatusFilter(): void  { $this->resetPage(); $this->selected = []; }
    public function updatedDateFrom(): void      { $this->resetPage(); $this->selected = []; }
    public function updatedDateTo(): void        { $this->resetPage(); $this->selected = []; }
    public function updatedEmployeeFilter(): void { $this->resetPage(); $this->selected = []; }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField     = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->claim_date = now()->toDateString();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $claim = OvertimeClaim::findOrFail($id);
        if ($claim->status !== 'draft') return;

        $this->editingId      = $claim->id;
        $this->employee_id    = $claim->employee_id;
        $this->claim_date     = $claim->claim_date->toDateString();
        $this->ot_time_start  = substr($claim->ot_time_start, 0, 5);
        $this->ot_time_end    = substr($claim->ot_time_end, 0, 5);
        $this->total_ot_hours = (string) floatval($claim->total_ot_hours);
        $this->ot_type        = $claim->ot_type;
        $this->reason         = $claim->reason;
        $this->showModal      = true;
    }

    public function save(string $action = 'save'): void
    {
        $this->validate();

        $user     = Auth::user();
        $outletId = $user->activeOutletId();

        $data = [
            'company_id'    => $user->company_id,
            'outlet_id'     => $outletId,
            'submitted_by'  => $user->id,
            'employee_id'   => $this->employee_id,
            'claim_date'    => $this->claim_date,
            'ot_time_start' => $this->ot_time_start,
            'ot_time_end'   => $this->ot_time_end,
            'total_ot_hours' => floatval($this->total_ot_hours),
            'ot_type'       => $this->ot_type,
            'reason'        => $this->reason,
            'status'        => $action === 'submit' ? 'submitted' : 'draft',
        ];

        if ($this->editingId) {
            $claim = OvertimeClaim::findOrFail($this->editingId);
            $claim->update($data);
        } else {
            OvertimeClaim::create($data);
        }

        $this->showModal = false;
        $this->resetForm();
        session()->flash('success', $action === 'submit' ? 'OT claim submitted for approval.' : 'OT claim saved as draft.');
    }

    public function submitClaim(int $id): void
    {
        $claim = OvertimeClaim::findOrFail($id);
        if ($claim->status !== 'draft') return;
        $claim->update(['status' => 'submitted']);
        session()->flash('success', 'OT claim submitted for approval.');
    }

    public function approveClaim(int $id): void
    {
        $claim = OvertimeClaim::with('employee')->findOrFail($id);
        if ($claim->status !== 'submitted') return;

        if (! OvertimeClaimApprover::isApproverFor(Auth::id(), $claim->outlet_id, $claim->employee?->section_id) && ! Auth::user()->isSystemRole()) {
            session()->flash('error', 'You are not authorized to approve this claim.');
            return;
        }

        $claim->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        session()->flash('success', 'OT claim approved.');
    }

    public function openReject(int $id): void
    {
        $this->rejectingId    = $id;
        $this->rejected_reason = '';
        $this->showRejectModal = true;
    }

    public function rejectClaim(): void
    {
        $this->validate(['rejected_reason' => 'required|string|max:500']);

        $claim = OvertimeClaim::with('employee')->findOrFail($this->rejectingId);
        if ($claim->status !== 'submitted') return;

        if (! OvertimeClaimApprover::isApproverFor(Auth::id(), $claim->outlet_id, $claim->employee?->section_id) && ! Auth::user()->isSystemRole()) {
            session()->flash('error', 'You are not authorized to reject this claim.');
            return;
        }

        $claim->update([
            'status'          => 'rejected',
            'approved_by'     => Auth::id(),
            'rejected_reason' => $this->rejected_reason,
        ]);

        $this->showRejectModal = false;
        session()->flash('success', 'OT claim rejected.');
    }

    public function deleteClaim(int $id): void
    {
        $claim = OvertimeClaim::findOrFail($id);
        $user  = Auth::user();

        // Admins (can_delete_records) can remove any claim regardless of status.
        // Everyone else can only delete drafts or rejected claims.
        $isAdminDelete = $user->hasCapability('can_delete_records');
        if (! $isAdminDelete && ! in_array($claim->status, ['draft', 'rejected'])) {
            session()->flash('error', 'Only drafts or rejected claims can be deleted. Ask an admin to remove approved claims.');
            return;
        }

        $claim->delete();
        session()->flash('success', 'OT claim deleted.');
    }

    // ── Bulk Actions ──

    public function bulkApprove(): void
    {
        if (empty($this->selected)) return;

        $user = Auth::user();
        $claims = OvertimeClaim::with('employee')
            ->whereIn('id', $this->selected)
            ->where('status', 'submitted')
            ->get();

        $count = 0;
        foreach ($claims as $claim) {
            if (OvertimeClaimApprover::isApproverFor($user->id, $claim->outlet_id, $claim->employee?->section_id) || $user->isSystemRole()) {
                $claim->update([
                    'status'      => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);
                $count++;
            }
        }

        $this->selected = [];
        session()->flash('success', "{$count} claim(s) approved.");
    }

    public function openBulkReject(): void
    {
        if (empty($this->selected)) return;
        $this->bulk_rejected_reason = '';
        $this->showBulkRejectModal  = true;
    }

    public function bulkReject(): void
    {
        $this->validate(['bulk_rejected_reason' => 'required|string|max:500']);

        $user = Auth::user();
        $claims = OvertimeClaim::with('employee')
            ->whereIn('id', $this->selected)
            ->where('status', 'submitted')
            ->get();

        $count = 0;
        foreach ($claims as $claim) {
            if (OvertimeClaimApprover::isApproverFor($user->id, $claim->outlet_id, $claim->employee?->section_id) || $user->isSystemRole()) {
                $claim->update([
                    'status'          => 'rejected',
                    'approved_by'     => $user->id,
                    'rejected_reason' => $this->bulk_rejected_reason,
                ]);
                $count++;
            }
        }

        $this->selected = [];
        $this->showBulkRejectModal = false;
        session()->flash('success', "{$count} claim(s) rejected.");
    }

    // ── Render ──

    public function render()
    {
        $user     = Auth::user();
        $outletId = $user->activeOutletId();

        // Outlet-level flag: does this user have ANY approver grant at this
        // outlet (regardless of section)? Drives UI gating (bulk bar, column
        // visibility). Per-claim section matching happens below.
        $isApprover = OvertimeClaimApprover::isApproverAtOutlet($user->id, $outletId)
            || $user->isSystemRole();
        $approverScopes = $user->isSystemRole()
            ? null  // sentinel: everything allowed
            : OvertimeClaimApprover::scopesForOutlet($user->id, $outletId);

        $query = OvertimeClaim::with(['employee.section', 'submitter', 'approver', 'outlet'])
            ->where('outlet_id', $outletId);

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->dateFrom) {
            $query->where('claim_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->where('claim_date', '<=', $this->dateTo);
        }
        if ($this->employeeFilter) {
            $query->where('employee_id', $this->employeeFilter);
        }
        if ($this->sectionFilter) {
            $query->whereIn('employee_id', function ($sub) {
                $sub->select('id')->from('employees')
                    ->where('section_id', (int) $this->sectionFilter);
            });
        }

        // Sorting
        if ($this->sortField === 'employee') {
            $query->join('employees', 'overtime_claims.employee_id', '=', 'employees.id')
                ->orderBy('employees.name', $this->sortDirection)
                ->select('overtime_claims.*');
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        $claims = $query->paginate($this->perPage);

        // Per-claim approve eligibility. System roles can approve everything;
        // everyone else is matched against their approver scopes (in-memory,
        // no extra queries per claim).
        $canApproveMap = [];
        foreach ($claims as $c) {
            if ($approverScopes === null) {
                $canApproveMap[$c->id] = true;
            } else {
                $canApproveMap[$c->id] = OvertimeClaimApprover::scopesMatch(
                    $approverScopes,
                    $c->outlet_id,
                    $c->employee?->section_id
                );
            }
        }

        // Employee list for dropdown (active only) and management modal (all)
        $allEmployees = Employee::with('section')
            ->where('outlet_id', $outletId)
            ->orderBy('name')
            ->get();
        $employees = $allEmployees->where('is_active', true);

        $sections = Section::active()->ordered()->get();

        // Company Admin / Business Manager / system roles can delete at any status.
        $canDeleteAny = $user->hasCapability('can_delete_records');

        // Stats
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd   = now()->endOfMonth()->toDateString();
        $monthStats = OvertimeClaim::where('outlet_id', $outletId)
            ->whereBetween('claim_date', [$monthStart, $monthEnd]);

        $totalHoursMonth   = (clone $monthStats)->where('status', 'approved')->sum('total_ot_hours');
        $pendingCount      = (clone $monthStats)->where('status', 'submitted')->count();
        $approvedCount     = (clone $monthStats)->where('status', 'approved')->count();

        // ── OT Trend — last 12 weeks (approved claims only) ──────────────────
        $trendWeeks = [];
        $thisWeekStart = now()->startOfWeek(\Carbon\Carbon::MONDAY);
        for ($i = 11; $i >= 0; $i--) {
            $ws = $thisWeekStart->copy()->subWeeks($i);
            $we = $ws->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
            $trendWeeks[] = [$ws->toDateString(), $we->toDateString()];
        }

        // Single query for the whole 12-week window, grouped by week start + type
        $trendFrom = $trendWeeks[0][0];
        $trendTo   = $trendWeeks[11][1];

        $rawTrend = OvertimeClaim::where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->whereBetween('claim_date', [$trendFrom, $trendTo])
            ->selectRaw("DATE(DATE_SUB(claim_date, INTERVAL (WEEKDAY(claim_date)) DAY)) as week_start,
                         ot_type,
                         SUM(total_ot_hours) as hours")
            ->groupByRaw("week_start, ot_type")
            ->get()
            ->groupBy('week_start');

        $trendLabels     = [];
        $trendNormalDay  = [];
        $trendPublicHol  = [];
        $trendRestDay    = [];

        foreach ($trendWeeks as [$ws, $we]) {
            $trendLabels[]    = \Carbon\Carbon::parse($ws)->format('d M');
            $rows             = $rawTrend->get($ws, collect())->keyBy('ot_type');
            $trendNormalDay[] = round((float) ($rows['normal_day']?->hours ?? 0), 2);
            $trendPublicHol[] = round((float) ($rows['public_holiday']?->hours ?? 0), 2);
            $trendRestDay[]   = round((float) ($rows['rest_day']?->hours ?? 0), 2);
        }

        // Week-on-week stats
        $thisWeekHours = $trendNormalDay[11] + $trendPublicHol[11] + $trendRestDay[11];
        $lastWeekHours = $trendNormalDay[10] + $trendPublicHol[10] + $trendRestDay[10];
        $wowChange     = $lastWeekHours > 0 ? round(($thisWeekHours - $lastWeekHours) / $lastWeekHours * 100, 1) : null;

        $weekTotals = array_map(fn ($i) => $trendNormalDay[$i] + $trendPublicHol[$i] + $trendRestDay[$i], range(0, 11));
        $peakWeekHours = max($weekTotals) ?: 0;
        $peakWeekLabel = $peakWeekHours > 0 ? $trendLabels[array_search($peakWeekHours, $weekTotals)] : null;
        $avgWeekHours  = count(array_filter($weekTotals)) > 0
            ? round(array_sum($weekTotals) / max(1, count(array_filter($weekTotals))), 1)
            : 0;

        // Top 5 employees by OT hours this month
        $topEmployees = OvertimeClaim::where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->whereBetween('claim_date', [$monthStart, $monthEnd])
            ->selectRaw('employee_id, SUM(total_ot_hours) as hours')
            ->groupBy('employee_id')
            ->orderByDesc('hours')
            ->limit(5)
            ->with('employee:id,name')
            ->get();

        $trendChartData = [
            'labels'  => $trendLabels,
            'normal'  => $trendNormalDay,
            'holiday' => $trendPublicHol,
            'rest'    => $trendRestDay,
        ];

        return view('livewire.hr.overtime-claims', compact(
            'claims', 'employees', 'allEmployees', 'sections', 'isApprover', 'canApproveMap', 'canDeleteAny',
            'totalHoursMonth', 'pendingCount', 'approvedCount',
            'trendChartData', 'thisWeekHours', 'lastWeekHours', 'wowChange',
            'peakWeekHours', 'peakWeekLabel', 'avgWeekHours', 'topEmployees'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Overtime Claims']);
    }

    // ── PDF Print ──

    public function openPdfModal(): void
    {
        $this->pdfFrom       = $this->dateFrom ?: now()->startOfMonth()->toDateString();
        $this->pdfTo         = $this->dateTo ?: now()->endOfMonth()->toDateString();
        $this->pdfEmployeeId = '';
        $this->showPdfModal  = true;
    }

    public function openSummaryModal(): void
    {
        $this->summaryMonth     = now()->format('m');
        $this->summaryYear      = now()->format('Y');
        $this->showSummaryModal = true;
    }

    public function getPdfUrl(): string
    {
        $params = ['from' => $this->pdfFrom, 'to' => $this->pdfTo];
        $employeeId = $this->pdfEmployeeId ?: 'all';

        return route('hr.ot-claims.pdf', ['employee' => $employeeId] + $params);
    }

    // ── Employee CRUD ──

    public function openAddEmployee(): void
    {
        $this->editingEmployeeId  = null;
        $this->emp_name           = '';
        $this->emp_designation    = '';
        $this->emp_section_id  = null;
        $this->showEmployeeModal  = true;
    }

    public function openEditEmployee(int $id): void
    {
        $emp = Employee::findOrFail($id);
        $this->editingEmployeeId  = $emp->id;
        $this->emp_name           = $emp->name;
        $this->emp_designation    = $emp->designation ?? '';
        $this->emp_section_id     = $emp->section_id;
        $this->showEmployeeModal  = true;
    }

    public function saveEmployee(): void
    {
        $this->validate([
            'emp_name'          => 'required|string|max:255',
            'emp_designation'   => 'nullable|string|max:255',
            'emp_section_id' => 'nullable|integer|exists:sections,id',
        ]);

        $user     = Auth::user();
        $outletId = $user->activeOutletId();

        $data = [
            'company_id'    => $user->company_id,
            'outlet_id'     => $outletId,
            'name'          => $this->emp_name,
            'designation'   => $this->emp_designation ?: null,
            'section_id' => $this->emp_section_id ?: null,
        ];

        if ($this->editingEmployeeId) {
            Employee::findOrFail($this->editingEmployeeId)->update($data);
            session()->flash('success', 'Employee updated.');
        } else {
            Employee::create($data);
            session()->flash('success', 'Employee added to list.');
        }

        $this->showEmployeeModal = false;
    }

    public function toggleEmployee(int $id): void
    {
        $emp = Employee::findOrFail($id);
        $emp->update(['is_active' => ! $emp->is_active]);
    }

    public function deleteEmployee(int $id): void
    {
        $emp = Employee::findOrFail($id);

        if (OvertimeClaim::where('employee_id', $id)->exists()) {
            session()->flash('error', 'Cannot delete employee with existing OT claims. Deactivate instead.');
            return;
        }

        $emp->delete();
        session()->flash('success', 'Employee removed.');
    }

    private function calcHours(): void
    {
        if (! $this->ot_time_start || ! $this->ot_time_end) return;
        try {
            $start = \Carbon\Carbon::createFromFormat('H:i', $this->ot_time_start);
            $end   = \Carbon\Carbon::createFromFormat('H:i', $this->ot_time_end);
            // Handle overnight shifts (e.g. 22:00 to 06:00)
            if ($end->lte($start)) {
                $end->addDay();
            }
            // Carbon 3 returns signed diffs — use start->end so we always get
            // a positive value (e.g. 19:00 → 20:00 = 60 minutes, not −60).
            $this->total_ot_hours = (string) round($start->diffInMinutes($end) / 60, 2);
        } catch (\Exception) {}
    }

    private function resetForm(): void
    {
        $this->editingId      = null;
        $this->employee_id    = null;
        $this->claim_date     = '';
        $this->ot_time_start  = '';
        $this->ot_time_end    = '';
        $this->total_ot_hours = '';
        $this->ot_type        = 'normal_day';
        $this->reason         = '';
    }
}
