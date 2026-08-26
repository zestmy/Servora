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
    public string $sectionFilter    = '';
    public string $employmentStatusFilter = ''; // '' all | status key | 'exclude_outsourcing' | 'none'
    public string $outletFilter     = '';
    public string $quickRange       = 'this_month';
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

    /** Narrow the list to the employee/date pairs flagged by the duplicate bar. */
    public bool   $showDuplicatesOnly = false;

    // PDF print modal
    public bool   $showPdfModal  = false;
    public string $pdfFrom       = '';
    public string $pdfTo         = '';
    public string $pdfEmployeeId = '';
    public string $pdfSectionId  = '';
    public string $pdfEmploymentStatus = ''; // same synthetic options as the list filter

    // Summary PDF modal — any date range, not just a whole month.
    public bool   $showSummaryModal = false;
    public string $summaryPeriod    = 'this_month'; // preset key | 'custom'
    public string $summaryFrom      = '';
    public string $summaryTo        = '';

    protected function rules(): array
    {
        return [
            'employee_id'    => 'required|exists:employees,id',
            'claim_date'     => 'required|date|before_or_equal:today',
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
            'claim_date.before_or_equal' => 'OT claim date cannot be in the future.',
            'ot_time_end.date_format' => 'Please enter a valid end time.',
            'total_ot_hours.min'     => 'Minimum OT is 0.25 hours (15 minutes).',
            'reason.required'        => 'Please provide a reason for the overtime.',
        ];
    }

    public function mount(): void
    {
        // Set default dates based on quick range
        $this->applyQuickRange($this->quickRange);

        // Set default outlet for multi-outlet users (no "All Outlets" option)
        $user = Auth::user();
        $availableOutletIds = $user->accessibleOutletIds();

        if (count($availableOutletIds) > 1 && empty($this->outletFilter)) {
            $this->outletFilter = (string) $availableOutletIds[0];
        }
    }

    public function updatedOtTimeStart(): void { $this->calcHours(); }
    public function updatedOtTimeEnd(): void   { $this->calcHours(); }

    public function updatedStatusFilter(): void   { $this->resetPage(); $this->selected = []; }
    public function updatedDateFrom(): void       { $this->resetPage(); $this->selected = []; $this->quickRange = 'custom'; }
    public function updatedDateTo(): void         { $this->resetPage(); $this->selected = []; $this->quickRange = 'custom'; }
    public function updatedEmployeeFilter(): void { $this->resetPage(); $this->selected = []; }
    public function updatedEmploymentStatusFilter(): void { $this->resetPage(); $this->selected = []; }
    public function updatedOutletFilter(): void   { $this->resetPage(); $this->selected = []; $this->employeeFilter = ''; }

    public function setQuickRange(string $range): void
    {
        $this->quickRange = $range;
        $this->applyQuickRange($range);
        $this->resetPage();
        $this->selected = [];
    }

    protected function applyQuickRange(string $range): void
    {
        $today = now();

        switch ($range) {
            case 'today':
                $this->dateFrom = $today->toDateString();
                $this->dateTo   = $today->toDateString();
                break;
            case 'yesterday':
                $this->dateFrom = $today->copy()->subDay()->toDateString();
                $this->dateTo   = $today->copy()->subDay()->toDateString();
                break;
            case 'last_7':
                $this->dateFrom = $today->copy()->subDays(6)->toDateString();
                $this->dateTo   = $today->toDateString();
                break;
            case 'this_week':
                $this->dateFrom = $today->copy()->startOfWeek()->toDateString();
                $this->dateTo   = $today->copy()->endOfWeek()->toDateString();
                break;
            case 'last_week':
                $this->dateFrom = $today->copy()->subWeek()->startOfWeek()->toDateString();
                $this->dateTo   = $today->copy()->subWeek()->endOfWeek()->toDateString();
                break;
            case 'this_month':
                $this->dateFrom = $today->copy()->startOfMonth()->toDateString();
                $this->dateTo   = $today->copy()->endOfMonth()->toDateString();
                break;
            case 'last_month':
                $this->dateFrom = $today->copy()->subMonth()->startOfMonth()->toDateString();
                $this->dateTo   = $today->copy()->subMonth()->endOfMonth()->toDateString();
                break;
            case 'this_year':
                $this->dateFrom = $today->copy()->startOfYear()->toDateString();
                $this->dateTo   = $today->copy()->endOfYear()->toDateString();
                break;
            case 'last_year':
                $this->dateFrom = $today->copy()->subYear()->startOfYear()->toDateString();
                $this->dateTo   = $today->copy()->subYear()->endOfYear()->toDateString();
                break;
            case 'all':
                $this->dateFrom = '';
                $this->dateTo   = '';
                break;
            // 'custom' - don't change dates
        }
    }

    /**
     * The employment-status branch, matching the Employees list and Attendance
     * Record grid ("All Exclude Outsourcing" and "No Status" are synthetic
     * options, not stored values). Shared by the claims list and every stats
     * aggregate so the cards and chart follow the visible rows.
     *
     * @param  string  $column  qualified when the query joins employees.
     */
    protected function applyEmploymentStatus($query, string $column = 'employment_status'): void
    {
        $this->currentFilter()->applyEmploymentStatus($query, $column);
    }

    /**
     * This screen's filters as one object, shared with the filtered PDF so the
     * export cannot narrow differently from the list it reproduces.
     */
    public function currentFilter(): \App\Services\Hr\OtClaimFilter
    {
        return \App\Services\Hr\OtClaimFilter::fromScreen(
            $this->statusFilter,
            $this->dateFrom,
            $this->dateTo,
            $this->employeeFilter,
            $this->sectionFilter,
            $this->employmentStatusFilter,
            $this->outletFilter,
            $this->sortField,
            $this->sortDirection,
        );
    }

    /**
     * The same narrowing as the list, MINUS the status filter.
     *
     * Duplication is a property of the records, not of the status you happen
     * to be looking at: a draft and an approved claim for one shift are the
     * dangerous pair, and a screen filtered to "Approved" would report the
     * view as clean. The stats cards exclude status for the same reason.
     */
    protected function duplicateFilter(): \App\Services\Hr\OtClaimFilter
    {
        return \App\Services\Hr\OtClaimFilter::fromScreen(
            '',
            $this->dateFrom,
            $this->dateTo,
            $this->employeeFilter,
            $this->sectionFilter,
            $this->employmentStatusFilter,
            $this->outletFilter,
        );
    }

    public function toggleDuplicatesOnly(): void
    {
        $this->showDuplicatesOnly = ! $this->showDuplicatesOnly;
        $this->resetPage();
        $this->selected = [];
    }

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
        $employee = Employee::find($this->employee_id);
        $outletId = $employee?->outlet_id ?? $user->activeOutletId();

        // A leaver stays claimable for the shifts they actually worked, but not
        // for days after they left — which is the whole reason they are still
        // in the picker rather than being hidden outright.
        $employedUntil = $employee?->employedUntil();
        if ($employedUntil && \Carbon\Carbon::parse($this->claim_date)->gt($employedUntil)) {
            $this->addError('claim_date', $employee->name . ' resigned on ' . $employedUntil->format('d M Y') . '. An OT claim cannot be dated after that.');
            return;
        }

        /*
         * One live claim per employee per date.
         *
         * The cost of the mistake is the reason for the gate: two claims for
         * the same shift are two lots of hours, and once both are approved
         * they are two lots of pay. Nothing downstream reconciles that — the
         * payslip adds them up.
         *
         * A REJECTED claim does not block, because a rejection means "fix this
         * and send it again"; see OvertimeClaim::BLOCKING_STATUSES.
         */
        if ($clash = OvertimeClaim::duplicateFor((int) $this->employee_id, $this->claim_date, $this->editingId)) {
            $this->addError('claim_date', sprintf(
                '%s already has a %s OT claim on %s (%s–%s, %sh). Edit that claim instead of raising a second one.',
                $employee?->name ?? 'This employee',
                $clash->status,
                $clash->claim_date->format('d M Y'),
                substr($clash->ot_time_start, 0, 5),
                substr($clash->ot_time_end, 0, 5),
                number_format((float) $clash->total_ot_hours, 1),
            ));
            return;
        }

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

    /**
     * Approve a claim, either for payroll or as time off.
     *
     * One method for both because the authorisation, the state check and the
     * audit fields are identical — only the settlement differs, and splitting
     * it into two would be two places to keep an approver check in step.
     *
     * $settlement NULL means "whatever this employee is normally on", which is
     * what the plain Approve button and the bulk action both send. That is
     * where the employee-level setting earns its keep: an approver working
     * through twenty claims does not have to remember which people are on
     * time-off terms.
     *
     * A non-null value is what the APPROVER explicitly pressed, and it is
     * checked against the allowed values rather than trusted — it arrives from
     * the browser and it decides whether somebody is paid in money or hours.
     */
    public function approveClaim(int $id, ?string $settlement = null): void
    {
        $claim = OvertimeClaim::with('employee')->findOrFail($id);
        if ($claim->status !== 'submitted') return;

        if (! OvertimeClaimApprover::isApproverFor(Auth::id(), $claim->employee?->outlet_id, $claim->employee?->section_id) && ! Auth::user()->isSystemRole()) {
            session()->flash('error', 'You are not authorized to approve this claim.');
            return;
        }

        $settlement = match ($settlement) {
            OvertimeClaim::SETTLE_TIME_OFF => OvertimeClaim::SETTLE_TIME_OFF,
            OvertimeClaim::SETTLE_PAYROLL  => OvertimeClaim::SETTLE_PAYROLL,
            // Anything else, including null and anything invented by a
            // hand-edited request, falls back to the employee's own terms.
            default => $claim->employee?->overtimeSettlementDefault() ?? OvertimeClaim::SETTLE_PAYROLL,
        };

        $claim->update([
            'status'      => 'approved',
            'settlement'  => $settlement,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        // Names the consequence rather than saying "approved". The two
        // outcomes are paid very differently and the approver has just chosen
        // between them — an identical message for both is how somebody
        // discovers next payday that they pressed the wrong one.
        session()->flash('success', $settlement === OvertimeClaim::SETTLE_TIME_OFF
            ? sprintf('Approved as time off — %s hours added to %s\'s time-off balance, not to payroll.',
                rtrim(rtrim(number_format((float) $claim->total_ot_hours, 2), '0'), '.'),
                $claim->employee?->name ?? 'the employee')
            : 'OT claim approved for payroll.');
    }

    /** The button beside Approve. Settlement is decided here, not in the view. */
    public function approveClaimAsTimeOff(int $id): void
    {
        $this->approveClaim($id, OvertimeClaim::SETTLE_TIME_OFF);
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

        if (! OvertimeClaimApprover::isApproverFor(Auth::id(), $claim->employee?->outlet_id, $claim->employee?->section_id) && ! Auth::user()->isSystemRole()) {
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
        $isAdminDelete = $user->canDo('hr.claims.delete');
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

        $count    = 0;
        $timeOff  = 0;

        foreach ($claims as $claim) {
            if (OvertimeClaimApprover::isApproverFor($user->id, $claim->employee?->outlet_id, $claim->employee?->section_id) || $user->isSystemRole()) {
                // Each claim settles on ITS OWN employee's terms. A bulk
                // approve spanning twenty people is exactly where nobody can
                // be expected to remember who is on time-off terms, which is
                // the whole reason the employee-level default exists.
                $settlement = $claim->employee?->overtimeSettlementDefault()
                    ?? OvertimeClaim::SETTLE_PAYROLL;

                $claim->update([
                    'status'      => 'approved',
                    'settlement'  => $settlement,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);

                $count++;
                $settlement === OvertimeClaim::SETTLE_TIME_OFF && $timeOff++;
            }
        }

        $this->selected = [];

        // Says how the batch split. "20 claims approved" hides the fact that
        // four of them will never appear on a payslip.
        session()->flash('success', $timeOff > 0
            ? sprintf('%d claim(s) approved — %d as time off, %d for payroll.',
                $count, $timeOff, $count - $timeOff)
            : "{$count} claim(s) approved.");
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
            if (OvertimeClaimApprover::isApproverFor($user->id, $claim->employee?->outlet_id, $claim->employee?->section_id) || $user->isSystemRole()) {
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
        $user = Auth::user();

        // Outlets this user can see. Cross-outlet roles (HR Manager, Business Manager)
        // get all company outlets; everyone else gets only their assigned outlets.
        $availableOutletIds = $user->accessibleOutletIds();

        // Approver checks match against every outlet the user can see, so an
        // outlet-specific approver assignment is honoured even for cross-outlet
        // users (a manager with can_view_all_outlets registered for one outlet
        // would otherwise match only wildcard rows and lose their button).
        $isApprover = $user->isSystemRole()
            || OvertimeClaimApprover::isApproverInOutlets($user->id, $availableOutletIds);
        $approverScopes = $user->isSystemRole()
            ? null  // sentinel: everything allowed
            : OvertimeClaimApprover::scopesForOutlets($user->id, $availableOutletIds);

        // Outlet list for filter dropdown (only shown when user has multiple outlets)
        $outlets = \App\Models\Outlet::whereIn('id', $availableOutletIds)->orderBy('name')->get();
        $multiOutlet = count($availableOutletIds) > 1;

        $scopedOutletIds = $this->currentFilter()->outletScope($availableOutletIds);

        /*
         * Every filter — including the outlet scope and the employee/claim
         * distinction — is applied through OtClaimFilter, because the filtered
         * PDF applies the SAME object. A document headed "matches your current
         * filter" that quietly used different rules would be worse than no
         * document: somebody signs it believing it is the list they were
         * looking at.
         *
         * Claims are matched on the EMPLOYEE's outlet rather than the claim's,
         * which on older records is not reliably where the person works. That
         * rule now lives in the filter object with the rest of them.
         */
        $query = OvertimeClaim::with(['employee.section', 'submitter', 'approver', 'outlet']);

        $this->currentFilter()->apply($query, $availableOutletIds);

        /*
         * Duplicate detection.
         *
         * Claims entered before the gate existed were legal when they were
         * made, so they are REPORTED rather than repaired — deciding which of
         * two claims for one shift is the real one is a human judgement, and
         * guessing it wrong loses somebody hours they worked.
         */
        $duplicateScope = OvertimeClaim::query();
        $this->duplicateFilter()->apply($duplicateScope, $availableOutletIds);
        $duplicateGroups = OvertimeClaim::duplicateGroups($duplicateScope);

        // Keyed for O(1) lookup per row in the table.
        $duplicateKeys = $duplicateGroups
            ->mapWithKeys(fn ($g) => [$g->employee_id . '|' . \Carbon\Carbon::parse($g->claim_date)->toDateString() => (int) $g->claim_count])
            ->all();

        $duplicateClaimCount = array_sum($duplicateKeys);

        if ($this->showDuplicatesOnly && $duplicateGroups->isNotEmpty()) {
            $query->where(function ($q) use ($duplicateGroups) {
                foreach ($duplicateGroups as $group) {
                    $q->orWhere(fn ($w) => $w
                        ->where('employee_id', $group->employee_id)
                        ->whereDate('claim_date', \Carbon\Carbon::parse($group->claim_date)->toDateString()));
                }
            });
        }

        // Sorting
        // Ordered through the same object as the filters, so the printed table
        // reads top-to-bottom exactly as this one does.
        $this->currentFilter()->applySort($query);

        $claims = $query->paginate($this->perPage);

        // Per-claim approve eligibility. System roles can approve everything;
        // everyone else is matched against their approver scopes (in-memory,
        // no extra queries per claim).
        // Use EMPLOYEE's outlet for approval matching, not claim's outlet
        $canApproveMap = [];
        foreach ($claims as $c) {
            if ($approverScopes === null) {
                $canApproveMap[$c->id] = true;
            } else {
                $canApproveMap[$c->id] = OvertimeClaimApprover::scopesMatch(
                    $approverScopes,
                    $c->employee?->outlet_id,
                    $c->employee?->section_id
                );
            }
        }

        // Calendar events (public holidays, etc.) covering the visible claim
        // dates — shown next to each date for context. A null-outlet event
        // applies to every outlet; onDate() narrows per claim's employee outlet.
        $visibleClaims  = $claims->getCollection();
        $calendarEvents = \App\Models\CalendarEvent::coveringRange(
            $scopedOutletIds,
            $visibleClaims->min('claim_date')?->toDateString(),
            $visibleClaims->max('claim_date')?->toDateString(),
        );

        // Employee list for dropdowns — scoped to selected outlet if filtered.
        // Include active employees plus any inactive employee who still has OT
        // claims, so a former employee's historical claims stay filterable while
        // inactive staff who never had OT don't clutter the list.
        $allEmployees = Employee::with('section')
            ->whereIn('outlet_id', $scopedOutletIds ?: [0])
            ->where(function ($q) {
                $q->where('is_active', true)
                  ->orWhereIn('id', function ($sub) {
                      $sub->select('employee_id')->from('overtime_claims')->whereNull('deleted_at');
                  });
            })
            ->orderBy('name')
            ->get();

        // Claimable staff: active, plus anyone who resigned — their last shifts
        // still have to be claimed and approved after they leave, and the claim
        // date is validated against the resignation date on save. Someone who
        // was simply deactivated is not claimable; that is not a leaving date.
        $employees = $allEmployees->filter(fn ($e) => $e->is_active || $e->hasResigned());

        $sections = Section::active()->ordered()->get();

        // Company Admin / Business Manager / system roles can delete at any status.
        $canDeleteAny = $user->canDo('hr.claims.delete');

        // Stats - use date range filters if set, otherwise current month
        $statsDateFrom = $this->dateFrom ?: now()->startOfMonth()->toDateString();
        $statsDateTo   = $this->dateTo ?: now()->endOfMonth()->toDateString();

        // ── OT by Section (for stats cards) ──────────────────────────────────
        // Query returns: section_name, total_hours, approved_hours, pending_hours
        // Filter by EMPLOYEE's outlet, not claim's outlet
        $sectionStats = OvertimeClaim::join('employees', 'overtime_claims.employee_id', '=', 'employees.id')
            ->whereIn('employees.outlet_id', $scopedOutletIds ?: [0])
            ->whereBetween('overtime_claims.claim_date', [$statsDateFrom, $statsDateTo])
            // Mirror the list's row filters so the cards match the visible claims.
            // Status is intentionally excluded — the cards ARE the status breakdown.
            ->when($this->employeeFilter, fn ($q) => $q->where('overtime_claims.employee_id', $this->employeeFilter))
            ->when($this->sectionFilter, fn ($q) => $q->where('employees.section_id', (int) $this->sectionFilter))
            ->when($this->employmentStatusFilter, fn ($q) => $this->applyEmploymentStatus($q, 'employees.employment_status'))
            ->leftJoin('sections', 'employees.section_id', '=', 'sections.id')
            ->selectRaw("COALESCE(sections.name, 'Unassigned') as section_name,
                SUM(CASE WHEN overtime_claims.status IN ('submitted', 'approved') THEN overtime_claims.total_ot_hours ELSE 0 END) as total_hours,
                SUM(CASE WHEN overtime_claims.status = 'approved' THEN overtime_claims.total_ot_hours ELSE 0 END) as approved_hours,
                SUM(CASE WHEN overtime_claims.status = 'submitted' THEN overtime_claims.total_ot_hours ELSE 0 END) as pending_hours,
                SUM(CASE WHEN overtime_claims.status = 'rejected' THEN overtime_claims.total_ot_hours ELSE 0 END) as rejected_hours")
            ->groupBy('sections.id', 'sections.name')
            ->orderBy('sections.name')
            ->get();

        // Calculate totals for each card
        $totalSubmittedHours = $sectionStats->sum('total_hours');
        $totalApprovedHours  = $sectionStats->sum('approved_hours');
        $totalPendingHours   = $sectionStats->sum('pending_hours');
        $totalRejectedHours  = $sectionStats->sum('rejected_hours');

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

        // Filter by EMPLOYEE's outlet, not claim's outlet.
        // Mirror the list's employee/section filters so the chart follows them.
        $rawTrend = OvertimeClaim::join('employees', 'overtime_claims.employee_id', '=', 'employees.id')
            ->whereIn('employees.outlet_id', $scopedOutletIds ?: [0])
            ->when($this->employeeFilter, fn ($q) => $q->where('overtime_claims.employee_id', $this->employeeFilter))
            ->when($this->sectionFilter, fn ($q) => $q->where('employees.section_id', (int) $this->sectionFilter))
            ->when($this->employmentStatusFilter, fn ($q) => $this->applyEmploymentStatus($q, 'employees.employment_status'))
            ->where('overtime_claims.status', 'approved')
            ->whereBetween('overtime_claims.claim_date', [$trendFrom, $trendTo])
            ->selectRaw("DATE(DATE_SUB(overtime_claims.claim_date, INTERVAL (WEEKDAY(overtime_claims.claim_date)) DAY)) as week_start,
                         overtime_claims.ot_type,
                         SUM(overtime_claims.total_ot_hours) as hours")
            ->groupByRaw("week_start, overtime_claims.ot_type")
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

        // Top 5 employees by OT hours PER SECTION (based on date filter),
        // shown side by side. Filter by EMPLOYEE's outlet, not claim's outlet;
        // mirrors the list's employee/section filters like the stats cards.
        $topBySection = OvertimeClaim::join('employees', 'overtime_claims.employee_id', '=', 'employees.id')
            ->leftJoin('sections', 'employees.section_id', '=', 'sections.id')
            ->whereIn('employees.outlet_id', $scopedOutletIds ?: [0])
            ->when($this->employeeFilter, fn ($q) => $q->where('overtime_claims.employee_id', $this->employeeFilter))
            ->when($this->sectionFilter, fn ($q) => $q->where('employees.section_id', (int) $this->sectionFilter))
            ->when($this->employmentStatusFilter, fn ($q) => $this->applyEmploymentStatus($q, 'employees.employment_status'))
            ->where('overtime_claims.status', 'approved')
            ->whereBetween('overtime_claims.claim_date', [$statsDateFrom, $statsDateTo])
            ->selectRaw("overtime_claims.employee_id,
                COALESCE(sections.name, 'Unassigned') as section_name,
                SUM(overtime_claims.total_ot_hours) as hours")
            ->groupBy('overtime_claims.employee_id', 'sections.name')
            ->orderByDesc('hours')
            ->get()
            ->load('employee:id,name')
            ->groupBy('section_name')
            ->map(fn ($rows) => $rows->take(5)->values());

        // Order the section columns like the Sections settings (sort order),
        // with any unmatched group (e.g. "Unassigned") last.
        $sectionOrder = $sections->pluck('name')->all();
        $topBySection = $topBySection->sortBy(function ($rows, $name) use ($sectionOrder) {
            $idx = array_search($name, $sectionOrder);
            return $idx === false ? PHP_INT_MAX : $idx;
        });

        $trendChartData = [
            'labels'  => $trendLabels,
            'normal'  => $trendNormalDay,
            'holiday' => $trendPublicHol,
            'rest'    => $trendRestDay,
        ];

        return view('livewire.hr.overtime-claims', compact(
            'claims', 'calendarEvents', 'employees', 'allEmployees', 'sections', 'outlets', 'multiOutlet',
            'isApprover', 'canApproveMap', 'canDeleteAny',
            'sectionStats', 'totalSubmittedHours', 'totalApprovedHours', 'totalPendingHours', 'totalRejectedHours',
            'statsDateFrom', 'statsDateTo',
            'trendChartData', 'thisWeekHours', 'lastWeekHours', 'wowChange',
            'peakWeekHours', 'peakWeekLabel', 'avgWeekHours', 'topBySection',
            'duplicateGroups', 'duplicateKeys', 'duplicateClaimCount'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Overtime Claims']);
    }

    // ── PDF Print ──

    public function openPdfModal(): void
    {
        $this->pdfFrom       = $this->dateFrom ?: now()->startOfMonth()->toDateString();
        $this->pdfTo         = $this->dateTo ?: now()->endOfMonth()->toDateString();
        $this->pdfEmployeeId = '';

        // Seeded from the list's own narrowing: someone who filtered to a
        // section and then hit Print meant that section, and having to pick it
        // twice is how the two quietly end up disagreeing.
        $this->pdfSectionId         = $this->sectionFilter;
        $this->pdfEmploymentStatus  = $this->employmentStatusFilter;

        $this->showPdfModal  = true;
    }

    /** Presets offered in the summary modal, alongside a free date range. */
    public const SUMMARY_PERIODS = [
        'this_month'   => 'This Month',
        'last_month'   => 'Last Month',
        'this_quarter' => 'This Quarter',
        'this_year'    => 'This Year',
        'last_year'    => 'Last Year',
    ];

    public function openSummaryModal(): void
    {
        // Start from whatever range the list is already showing — the report
        // then matches the claims on screen. Only fall back to this month when
        // the list is on "All" (no dates).
        if ($this->dateFrom && $this->dateTo) {
            $this->summaryPeriod = 'custom';
            $this->summaryFrom   = $this->dateFrom;
            $this->summaryTo     = $this->dateTo;
        } else {
            $this->setSummaryPeriod('this_month');
        }

        $this->showSummaryModal = true;
    }

    public function setSummaryPeriod(string $period): void
    {
        $this->summaryPeriod = $period;
        $today = now();

        [$from, $to] = match ($period) {
            'this_month'   => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_month'   => [$today->copy()->subMonth()->startOfMonth(), $today->copy()->subMonth()->endOfMonth()],
            'this_quarter' => [$today->copy()->startOfQuarter(), $today->copy()->endOfQuarter()],
            'this_year'    => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'last_year'    => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
            default        => [null, null], // 'custom' — leave the dates alone
        };

        if ($from && $to) {
            $this->summaryFrom = $from->toDateString();
            $this->summaryTo   = $to->toDateString();
        }
    }

    // Typing a date by hand drops the preset highlight.
    public function updatedSummaryFrom(): void { $this->summaryPeriod = 'custom'; }
    public function updatedSummaryTo(): void   { $this->summaryPeriod = 'custom'; }

    public function getPdfUrl(): string
    {
        $params = array_filter([
            'from'       => $this->pdfFrom,
            'to'         => $this->pdfTo,
            'outlet'     => $this->outletFilter,
            'section'    => $this->pdfSectionId,
            'employment' => $this->pdfEmploymentStatus,
        ], fn ($v) => $v !== '' && $v !== null);

        $employeeId = $this->pdfEmployeeId ?: 'all';

        return route('hr.ot-claims.pdf', ['employee' => $employeeId] + $params);
    }

    public function getSummaryPdfUrl(): string
    {
        return route('hr.ot-claims.summary-pdf', array_filter([
            'from'   => $this->summaryFrom,
            'to'     => $this->summaryTo,
            'outlet' => $this->outletFilter,
        ]));
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
