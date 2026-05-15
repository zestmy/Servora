<?php

namespace App\Livewire\Hr;

use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Roster;
use App\Models\RosterAmendment;
use App\Models\RosterApprover;
use App\Models\RosterDayRemark;
use App\Models\RosterEmailRecipient;
use App\Models\RosterEntry;
use App\Models\RosterSetting;
use App\Models\RosterStation;
use App\Services\RosterEmailService;
use App\Services\RosterPdfService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DutyRoster extends Component
{
    public ?int $outletId = null;
    public string $weekStart = '';
    public string $weekEnd = '';

    public ?Roster $roster = null;

    // Entry form modal
    public bool $showEntryForm = false;
    public ?int $editingEntryId = null;
    public ?int $f_employee_id = null;
    public string $f_day_date = '';
    public ?int $f_station_id = null;
    public string $f_shift_start = '';
    public string $f_shift_end = '';
    public int $f_rest_duration = 60;
    public bool $f_is_off_day = false;
    public string $f_leave_type = 'off';
    public string $f_planned_ot = '';
    public bool $f_planned_ot_manual = false;
    public string $f_notes = '';

    // Day remark modal
    public bool $showRemarkForm = false;
    public string $remark_date = '';
    public string $remark_type = 'custom';
    public string $remark_text = '';

    // Email modal
    public bool $showEmailModal = false;
    public bool $email_to_employees = true;
    public array $email_recipient_ids = [];
    public string $email_additional = '';

    // Amendment modal (for approved rosters)
    public bool $showAmendmentForm = false;
    public string $amendment_reason = '';

    // Rejection modal
    public bool $showRejectModal = false;
    public string $rejection_reason = '';

    protected function rules(): array
    {
        return [
            'f_employee_id' => 'required|exists:employees,id',
            'f_day_date' => 'required|date',
            'f_station_id' => 'nullable|exists:roster_stations,id',
            'f_shift_start' => 'nullable|date_format:H:i',
            'f_shift_end' => 'nullable|date_format:H:i',
            'f_rest_duration' => 'integer|min:0|max:480',
            'f_is_off_day' => 'boolean',
            'f_leave_type' => 'nullable|string|in:off,al,rph,mc,rdo,ch',
            'f_planned_ot' => 'nullable|numeric|min:0|max:24',
            'f_planned_ot_manual' => 'boolean',
            'f_notes' => 'nullable|string|max:255',
        ];
    }

    public function mount(): void
    {
        $this->outletId = Auth::user()?->activeOutletId();
        $this->setCurrentWeek();
        $this->loadRoster();
    }

    protected function setCurrentWeek(): void
    {
        $now = now();
        $this->weekStart = $now->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        $this->weekEnd = $now->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');
    }

    public function updatedOutletId(): void
    {
        $this->loadRoster();
    }

    public function previousWeek(): void
    {
        $start = Carbon::parse($this->weekStart)->subWeek();
        $this->weekStart = $start->format('Y-m-d');
        $this->weekEnd = $start->copy()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');
        $this->loadRoster();
    }

    public function nextWeek(): void
    {
        $start = Carbon::parse($this->weekStart)->addWeek();
        $this->weekStart = $start->format('Y-m-d');
        $this->weekEnd = $start->copy()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');
        $this->loadRoster();
    }

    protected function loadRoster(): void
    {
        $this->roster = null;

        if (!$this->outletId) {
            return;
        }

        $this->roster = Roster::where('outlet_id', $this->outletId)
            ->where('week_start_date', $this->weekStart)
            ->whereNull('section_id')
            ->with(['entries.employee', 'entries.station', 'dayRemarks', 'amendments'])
            ->first();
    }

    public function createRoster(): void
    {
        if (!$this->outletId) {
            return;
        }

        $this->roster = Roster::create([
            'company_id' => Auth::user()->company_id,
            'created_by' => Auth::id(),
            'outlet_id' => $this->outletId,
            'section_id' => null,
            'week_start_date' => $this->weekStart,
            'week_end_date' => $this->weekEnd,
            'status' => Roster::STATUS_DRAFT,
            'revision' => 1,
        ]);

        // Pre-populate with active employees from the outlet/section
        $this->prepopulateEmployees();

        $this->roster->load(['entries.employee', 'entries.station', 'dayRemarks']);
        session()->flash('success', 'Roster created with employees pre-populated.');
    }

    /**
     * Pre-populate roster with placeholder entries for all active employees.
     */
    protected function prepopulateEmployees(): void
    {
        // Order by section first, then by name
        $employees = Employee::where('outlet_id', $this->outletId)
            ->where('is_active', true)
            ->orderBy('section_id')
            ->orderBy('name')
            ->get();
        $weekDays = $this->getWeekDays();

        // Get default rest duration from settings
        $settings = RosterSetting::firstOrCreate(
            ['outlet_id' => $this->outletId],
            ['normal_hours' => 8.00, 'rest_duration' => 60]
        );

        $sortOrder = 0;
        foreach ($employees as $employee) {
            // Create a placeholder entry for first day to show employee in roster
            // This entry starts as "off day" so user can click to edit shifts
            RosterEntry::create([
                'roster_id' => $this->roster->id,
                'employee_id' => $employee->id,
                'day_date' => $weekDays[0]['date'],
                'is_off_day' => true,
                'leave_type' => 'off',
                'rest_duration' => $settings->rest_duration,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    /**
     * Reorder employees via drag-and-drop.
     */
    public function reorderEmployees(array $orderedIds): void
    {
        if (!$this->roster || !$this->roster->isDraft()) {
            return;
        }

        foreach ($orderedIds as $index => $employeeId) {
            RosterEntry::where('roster_id', $this->roster->id)
                ->where('employee_id', $employeeId)
                ->update(['sort_order' => $index]);
        }

        $this->loadRoster();
    }

    /**
     * Remove an employee and all their entries from the roster.
     */
    public function removeEmployeeRow(int $employeeId): void
    {
        if (!$this->roster || !$this->roster->isDraft()) {
            session()->flash('error', 'Cannot remove employees from non-draft rosters.');
            return;
        }

        RosterEntry::where('roster_id', $this->roster->id)
            ->where('employee_id', $employeeId)
            ->delete();

        $this->loadRoster();
        session()->flash('success', 'Employee removed from roster.');
    }

    // Entry Form Methods
    public function openAddEntry(string $date, ?int $employeeId = null): void
    {
        if (!$this->roster || !$this->roster->isDraft()) {
            return;
        }

        $this->resetEntryForm();
        $this->f_day_date = $date;
        $this->f_employee_id = $employeeId;

        // Load default rest duration from settings
        $settings = RosterSetting::firstOrCreate(
            ['outlet_id' => $this->outletId],
            ['normal_hours' => 8.00, 'rest_duration' => 60]
        );
        $this->f_rest_duration = $settings->rest_duration;

        $this->showEntryForm = true;
    }

    public function openEditEntry(int $entryId): void
    {
        $entry = RosterEntry::findOrFail($entryId);

        // Check if editing approved roster (requires amend permission)
        if ($this->roster && $this->roster->isApproved()) {
            if (!Auth::user()->can('roster.amend')) {
                session()->flash('error', 'You do not have permission to amend approved rosters.');
                return;
            }
        } elseif ($this->roster && !$this->roster->isDraft()) {
            session()->flash('error', 'Cannot edit entries in submitted roster.');
            return;
        }

        $this->editingEntryId = $entry->id;
        $this->f_employee_id = $entry->employee_id;
        $this->f_day_date = $entry->day_date->format('Y-m-d');
        $this->f_station_id = $entry->station_id;
        $this->f_shift_start = $entry->shift_start ? Carbon::parse($entry->shift_start)->format('H:i') : '';
        $this->f_shift_end = $entry->shift_end ? Carbon::parse($entry->shift_end)->format('H:i') : '';
        $this->f_rest_duration = $entry->rest_duration;
        $this->f_is_off_day = $entry->is_off_day;
        $this->f_leave_type = $entry->leave_type ?? 'off';
        $this->f_planned_ot = $entry->planned_ot_manual ? (string) $entry->planned_ot : '';
        $this->f_planned_ot_manual = $entry->planned_ot_manual;
        $this->f_notes = $entry->notes ?? '';

        $this->showEntryForm = true;
    }

    public function closeEntryForm(): void
    {
        $this->showEntryForm = false;
        $this->showAmendmentForm = false;
        $this->amendment_reason = '';
        $this->resetEntryForm();
    }

    protected function resetEntryForm(): void
    {
        $this->editingEntryId = null;
        $this->f_employee_id = null;
        $this->f_day_date = '';
        $this->f_station_id = null;
        $this->f_shift_start = '';
        $this->f_shift_end = '';
        $this->f_rest_duration = 60;
        $this->f_is_off_day = false;
        $this->f_leave_type = 'off';
        $this->f_planned_ot = '';
        $this->f_planned_ot_manual = false;
        $this->f_notes = '';
        $this->resetValidation();
    }

    public function saveEntry(): void
    {
        // Validate fields
        $this->validate([
            'f_employee_id' => 'required|exists:employees,id',
            'f_day_date' => 'required|date',
        ]);

        // For approved rosters, require amendment reason
        if ($this->roster && $this->roster->isApproved()) {
            if (empty($this->amendment_reason)) {
                $this->showAmendmentForm = true;
                return;
            }
        }

        $data = [
            'roster_id' => $this->roster->id,
            'employee_id' => $this->f_employee_id,
            'station_id' => $this->f_station_id,
            'day_date' => $this->f_day_date,
            'shift_start' => $this->f_is_off_day ? null : ($this->f_shift_start ?: null),
            'shift_end' => $this->f_is_off_day ? null : ($this->f_shift_end ?: null),
            'rest_duration' => $this->f_rest_duration,
            'is_off_day' => $this->f_is_off_day,
            'leave_type' => $this->f_is_off_day ? $this->f_leave_type : null,
            'planned_ot_manual' => $this->f_planned_ot_manual,
            'planned_ot' => $this->f_planned_ot_manual ? (float) $this->f_planned_ot : 0,
            'notes' => $this->f_notes ?: null,
        ];

        if ($this->editingEntryId) {
            $entry = RosterEntry::findOrFail($this->editingEntryId);
            $oldData = $entry->toArray();
            $entry->update($data);

            // If approved roster, create amendment record
            if ($this->roster->isApproved()) {
                $changes = $this->detectChanges($oldData, $entry->fresh()->toArray());
                if (!empty($changes)) {
                    RosterAmendment::create([
                        'roster_id' => $this->roster->id,
                        'roster_entry_id' => $entry->id,
                        'amended_by' => Auth::id(),
                        'reason' => $this->amendment_reason,
                        'changes' => $changes,
                    ]);

                    $this->roster->incrementRevision($this->amendment_reason);
                }
            }

            session()->flash('success', 'Entry updated.');
        } else {
            // Check for duplicate
            $exists = RosterEntry::where('roster_id', $this->roster->id)
                ->where('employee_id', $this->f_employee_id)
                ->where('day_date', $this->f_day_date)
                ->exists();

            if ($exists) {
                $this->addError('f_employee_id', 'This employee already has an entry for this day.');
                return;
            }

            RosterEntry::create($data);
            session()->flash('success', 'Entry added.');
        }

        // Update last edited tracking
        $this->roster->update([
            'last_edited_by' => Auth::id(),
            'last_edited_at' => now(),
        ]);

        $this->loadRoster();
        $this->closeEntryForm();
    }

    protected function detectChanges(array $old, array $new): array
    {
        $fields = ['shift_start', 'shift_end', 'rest_duration', 'station_id', 'is_off_day', 'planned_ot'];
        $changes = [];

        foreach ($fields as $field) {
            if (($old[$field] ?? null) !== ($new[$field] ?? null)) {
                $changes[$field] = [
                    'from' => $old[$field] ?? null,
                    'to' => $new[$field] ?? null,
                ];
            }
        }

        return $changes;
    }

    public function confirmAmendment(): void
    {
        if (empty($this->amendment_reason)) {
            $this->addError('amendment_reason', 'Please provide a reason for this amendment.');
            return;
        }

        $this->saveEntry();
    }

    public function deleteEntry(int $id): void
    {
        if (!$this->roster || !$this->roster->isDraft()) {
            session()->flash('error', 'Cannot delete entries from non-draft rosters.');
            return;
        }

        RosterEntry::findOrFail($id)->delete();
        $this->loadRoster();
        session()->flash('success', 'Entry removed.');
    }

    // Day Remark Methods
    public function openRemarkForm(string $date): void
    {
        if (!$this->roster || !$this->roster->isDraft()) {
            return;
        }

        $this->remark_date = $date;
        $this->remark_type = 'custom';
        $this->remark_text = '';

        // Check if remark exists
        $existing = $this->roster->dayRemarks->where('day_date', Carbon::parse($date))->first();
        if ($existing) {
            $this->remark_type = $existing->remark_type;
            $this->remark_text = $existing->remark_text;
        }

        $this->showRemarkForm = true;
    }

    public function closeRemarkForm(): void
    {
        $this->showRemarkForm = false;
        $this->remark_date = '';
        $this->remark_type = 'custom';
        $this->remark_text = '';
    }

    public function saveRemark(): void
    {
        $this->validate([
            'remark_date' => 'required|date',
            'remark_type' => 'required|in:public_holiday,stocktake,event,custom',
            'remark_text' => 'required|string|max:255',
        ]);

        RosterDayRemark::updateOrCreate(
            ['roster_id' => $this->roster->id, 'day_date' => $this->remark_date],
            ['remark_type' => $this->remark_type, 'remark_text' => $this->remark_text]
        );

        $this->loadRoster();
        $this->closeRemarkForm();
        session()->flash('success', 'Day remark saved.');
    }

    public function deleteRemark(string $date): void
    {
        if (!$this->roster || !$this->roster->isDraft()) {
            return;
        }

        RosterDayRemark::where('roster_id', $this->roster->id)
            ->where('day_date', $date)
            ->delete();

        $this->loadRoster();
        session()->flash('success', 'Day remark removed.');
    }

    // Workflow Methods
    public function submitRoster(): void
    {
        if (!$this->roster || !$this->roster->isDraft()) {
            return;
        }

        if ($this->roster->entries->isEmpty()) {
            session()->flash('error', 'Cannot submit an empty roster.');
            return;
        }

        $this->roster->submit(Auth::id());
        $this->loadRoster();
        session()->flash('success', 'Roster submitted for approval.');
    }

    public function approveRoster(): void
    {
        if (!$this->roster || !$this->roster->isSubmitted()) {
            return;
        }

        if (!$this->canApprove()) {
            session()->flash('error', 'You do not have permission to approve this roster.');
            return;
        }

        $this->roster->approve(Auth::id());
        $this->loadRoster();
        session()->flash('success', 'Roster approved. Pending OT claims have been created.');
    }

    public function openRejectModal(): void
    {
        $this->rejection_reason = '';
        $this->showRejectModal = true;
    }

    public function closeRejectModal(): void
    {
        $this->showRejectModal = false;
        $this->rejection_reason = '';
    }

    public function rejectRoster(): void
    {
        if (!$this->roster || !$this->roster->isSubmitted()) {
            return;
        }

        if (!$this->canApprove()) {
            session()->flash('error', 'You do not have permission to reject this roster.');
            return;
        }

        if (empty($this->rejection_reason)) {
            $this->addError('rejection_reason', 'Please provide a rejection reason.');
            return;
        }

        $this->roster->reject(Auth::id(), $this->rejection_reason);
        $this->loadRoster();
        $this->closeRejectModal();
        session()->flash('success', 'Roster rejected.');
    }

    public function revertToDraft(): void
    {
        if (!$this->roster) {
            return;
        }

        if ($this->roster->isApproved()) {
            session()->flash('error', 'Cannot revert an approved roster to draft.');
            return;
        }

        $this->roster->revertToDraft();
        $this->loadRoster();
        session()->flash('success', 'Roster reverted to draft.');
    }

    protected function canApprove(): bool
    {
        $user = Auth::user();

        // Check if user has roster.approve permission
        if ($user->can('roster.approve')) {
            return true;
        }

        // Check if user is an approver for this outlet
        return RosterApprover::where('outlet_id', $this->outletId)
            ->where('user_id', $user->id)
            ->exists();
    }

    // Export PDF
    public function exportPdf()
    {
        if (!$this->roster) {
            return;
        }

        $pdf = RosterPdfService::generate($this->roster);
        $filename = "duty-roster-{$this->weekStart}.pdf";

        return response()->streamDownload(fn () => print($pdf->output()), $filename);
    }

    // Email Methods
    public function openEmailModal(): void
    {
        if (!$this->roster) {
            return;
        }

        $this->email_to_employees = true;
        $this->email_recipient_ids = [];
        $this->email_additional = '';
        $this->showEmailModal = true;
    }

    public function closeEmailModal(): void
    {
        $this->showEmailModal = false;
    }

    public function sendEmail(): void
    {
        if (!$this->roster) {
            return;
        }

        $additionalEmails = array_filter(
            array_map('trim', explode(',', $this->email_additional))
        );

        $result = RosterEmailService::send(
            roster: $this->roster,
            sendToEmployees: $this->email_to_employees,
            customRecipientIds: $this->email_recipient_ids,
            additionalEmails: $additionalEmails
        );

        if ($result['success']) {
            session()->flash('success', $result['message']);
        } else {
            session()->flash('error', $result['message']);
        }

        $this->closeEmailModal();
    }

    // Helper Methods
    protected function accessibleOutlets()
    {
        $user = Auth::user();
        if ($user->canViewAllOutlets()) {
            return Outlet::where('company_id', $user->company_id)->orderBy('name')->get();
        }
        return $user->outlets()->orderBy('name')->get();
    }

    protected function getWeekDays(): array
    {
        $days = [];
        $start = Carbon::parse($this->weekStart);
        $end = Carbon::parse($this->weekEnd);

        while ($start->lte($end)) {
            $days[] = [
                'date' => $start->format('Y-m-d'),
                'dayName' => $start->format('D'),
                'dayNum' => $start->format('j'),
            ];
            $start->addDay();
        }

        return $days;
    }

    protected function getEntriesGrouped(): array
    {
        if (!$this->roster) {
            return [];
        }

        $grouped = [];

        // Get normal hours setting for this outlet
        $settings = RosterSetting::where('outlet_id', $this->outletId)->first();
        $normalHours = $settings?->normal_hours ?? 8.00;

        // Get the minimum sort_order for each employee to determine their position
        $employeeSortOrders = [];

        foreach ($this->roster->entries as $entry) {
            $empId = $entry->employee_id;
            $dateKey = $entry->day_date->format('Y-m-d');

            if (!isset($grouped[$empId])) {
                $grouped[$empId] = [
                    'employee' => $entry->employee,
                    'section' => $entry->employee?->section,
                    'entries' => [],
                    'total_hours' => 0,
                    'regular_hours' => 0,
                    'total_ot' => 0,
                    'sort_order' => $entry->sort_order ?? 0,
                ];
                $employeeSortOrders[$empId] = $entry->sort_order ?? 0;
            }

            // Keep track of minimum sort_order for this employee
            if (($entry->sort_order ?? 0) < $employeeSortOrders[$empId]) {
                $employeeSortOrders[$empId] = $entry->sort_order ?? 0;
                $grouped[$empId]['sort_order'] = $entry->sort_order ?? 0;
            }

            $grouped[$empId]['entries'][$dateKey] = $entry;
            $grouped[$empId]['total_hours'] += (float) $entry->hours_worked;
            $grouped[$empId]['total_ot'] += (float) $entry->planned_ot;

            // Calculate regular hours (capped at normal hours per day)
            $entryRegular = min((float) $entry->hours_worked, (float) $normalHours);
            $grouped[$empId]['regular_hours'] += $entryRegular;
        }

        // Sort by sort_order
        uasort($grouped, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        return $grouped;
    }

    /**
     * Get entries grouped by section then by employee.
     */
    protected function getEntriesBySection(): array
    {
        $entriesGrouped = $this->getEntriesGrouped();

        $bySection = [];

        foreach ($entriesGrouped as $empId => $empData) {
            $sectionId = $empData['section']?->id ?? 0;
            $sectionName = $empData['section']?->name ?? 'No Section';

            if (!isset($bySection[$sectionId])) {
                $bySection[$sectionId] = [
                    'section_id' => $sectionId,
                    'section_name' => $sectionName,
                    'employees' => [],
                ];
            }

            $bySection[$sectionId]['employees'][$empId] = $empData;
        }

        // Sort sections by name (but keep "No Section" at the end)
        uasort($bySection, function ($a, $b) {
            if ($a['section_id'] === 0) return 1;
            if ($b['section_id'] === 0) return -1;
            return strcmp($a['section_name'], $b['section_name']);
        });

        return $bySection;
    }

    public function render()
    {
        $outlets = $this->accessibleOutlets();
        $weekDays = $this->getWeekDays();
        $entriesGrouped = $this->getEntriesGrouped();
        $entriesBySection = $this->getEntriesBySection();

        $employees = collect();
        $stations = collect();
        $dayRemarks = collect();
        $emailRecipients = collect();

        if ($this->outletId) {
            $employees = Employee::where('outlet_id', $this->outletId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $stations = RosterStation::where('outlet_id', $this->outletId)->active()->ordered()->get();
            $emailRecipients = RosterEmailRecipient::where('outlet_id', $this->outletId)->active()->get();
        }

        if ($this->roster) {
            $dayRemarks = $this->roster->dayRemarks->keyBy(fn ($r) => $r->day_date->format('Y-m-d'));
        }

        $periodLabel = Carbon::parse($this->weekStart)->format('M d') . ' - ' . Carbon::parse($this->weekEnd)->format('M d, Y');

        $canApprove = $this->canApprove();
        $canAmend = Auth::user()->can('roster.amend');

        // Leave types for the form
        $leaveTypes = RosterEntry::LEAVE_TYPES;

        return view('livewire.hr.duty-roster', compact(
            'outlets', 'weekDays', 'entriesGrouped', 'entriesBySection', 'employees', 'stations',
            'dayRemarks', 'emailRecipients', 'periodLabel', 'canApprove', 'canAmend', 'leaveTypes'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Duty Roster']);
    }
}
