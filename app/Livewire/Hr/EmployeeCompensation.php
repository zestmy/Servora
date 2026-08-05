<?php

namespace App\Livewire\Hr;

use App\Models\CompensationSetting;
use App\Models\Employee;
use App\Models\EmployeePayComponent;
use App\Models\EmployeeStatutoryProfile;
use App\Models\PayComponent;
use App\Models\SalaryRevision;
use App\Models\StatutorySetting;
use App\Services\Hr\CompensationSummary;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * One employee's compensation: their allowances and deductions, and the
 * history of changes to their basic salary.
 *
 * Basic salary is NOT editable here. It changes through a dated revision that
 * someone else signs off — that is the point of having a history, and an
 * inline field would be a second door onto the same number.
 */
class EmployeeCompensation extends Component
{
    public Employee $employee;

    // Assign a component
    public bool   $showAssign     = false;
    public ?int   $assignmentId   = null;
    public string $a_component_id = '';
    public string $a_amount       = '';
    public string $a_from         = '';
    public string $a_to           = '';
    public string $a_notes        = '';

    // Request a salary revision
    public bool   $showRevision   = false;
    public string $r_amount       = '';
    public string $r_pay_type     = 'monthly';
    public string $r_effective_on = '';
    public string $r_reason       = '';

    // Statutory profile
    public bool   $showStatutory  = false;
    public string $s_epf_number   = '';
    public string $s_socso_number = '';
    public string $s_tax_number   = '';
    public bool   $s_is_malaysian = true;
    public bool   $s_epf          = true;
    public bool   $s_socso        = true;
    public bool   $s_eis          = true;
    public bool   $s_pcb          = true;
    public string $s_epf_override = '';
    public string $s_pcb_category = 'single';
    public string $s_children     = '0';
    public string $s_zakat        = '0';
    public string $s_other_relief = '0';
    public string $s_date_of_birth = '';
    // Payroll paperwork: the IC identifies the employee on every statutory
    // submission, and the bank pair is what a salary transfer file needs.
    public string $s_ic_number      = '';
    public string $s_bank_name      = '';
    public string $s_bank_account   = '';

    public function mount(int $id): void
    {
        $employee = Employee::findOrFail($id);

        if (! in_array((int) $employee->outlet_id, Auth::user()->accessibleOutletIds(), true)) {
            abort(403, 'You do not have access to this employee.');
        }

        $this->employee = $employee;
        $this->loadStatutoryProfile();
    }

    private function loadStatutoryProfile(): void
    {
        $p = EmployeeStatutoryProfile::forEmployee($this->employee);

        $this->s_epf_number    = $p->epf_number ?? '';
        $this->s_socso_number  = $p->socso_number ?? '';
        $this->s_tax_number    = $p->income_tax_number ?? '';
        $this->s_is_malaysian  = (bool) $p->is_malaysian;
        $this->s_epf           = (bool) $p->epf_enabled;
        $this->s_socso         = (bool) $p->socso_enabled;
        $this->s_eis           = (bool) $p->eis_enabled;
        $this->s_pcb           = (bool) $p->pcb_enabled;
        $this->s_epf_override  = $p->epf_employee_rate_override !== null ? (string) (float) $p->epf_employee_rate_override : '';
        $this->s_pcb_category  = $p->pcb_category ?: 'single';
        $this->s_children      = (string) $p->children;
        $this->s_zakat         = (string) (float) $p->monthly_zakat;
        $this->s_other_relief  = (string) (float) $p->annual_other_relief;
        $this->s_date_of_birth = $this->employee->date_of_birth?->format('Y-m-d') ?? '';
        $this->s_ic_number     = (string) ($this->employee->ic_number ?? '');
        $this->s_bank_name     = (string) ($this->employee->bank_name ?? '');
        $this->s_bank_account  = (string) ($this->employee->bank_account_no ?? '');
    }

    public function saveStatutoryProfile(): void
    {
        $this->validate([
            's_epf_number'    => 'nullable|string|max:30',
            's_socso_number'  => 'nullable|string|max:30',
            's_tax_number'    => 'nullable|string|max:30',
            's_epf_override'  => 'nullable|numeric|min:0|max:100',
            's_pcb_category'  => 'required|in:' . implode(',', array_keys(StatutorySetting::PCB_CATEGORIES)),
            's_children'      => 'required|integer|min:0|max:50',
            's_zakat'         => 'required|numeric|min:0|max:1000000',
            's_other_relief'  => 'required|numeric|min:0|max:1000000',
            's_date_of_birth' => 'nullable|date|before:today',
            's_ic_number'     => 'nullable|string|max:20',
            's_bank_name'     => 'nullable|string|max:60',
            's_bank_account'  => 'nullable|string|max:40',
        ]);

        // These live on the employee rather than the statutory profile: EPF and
        // SOCSO rates both change at 60, so date of birth is not statutory-only,
        // and the IC and bank pair identify the person rather than describe a
        // contribution.
        $this->employee->update([
            'date_of_birth'   => $this->s_date_of_birth ?: null,
            'ic_number'       => $this->s_ic_number ?: null,
            'bank_name'       => $this->s_bank_name ?: null,
            'bank_account_no' => $this->s_bank_account ?: null,
        ]);

        EmployeeStatutoryProfile::updateOrCreate(
            ['employee_id' => $this->employee->id],
            [
                'company_id'        => $this->employee->company_id,
                'epf_number'        => $this->s_epf_number ?: null,
                'socso_number'      => $this->s_socso_number ?: null,
                'income_tax_number' => $this->s_tax_number ?: null,
                'is_malaysian'      => $this->s_is_malaysian,
                'epf_enabled'       => $this->s_epf,
                'socso_enabled'     => $this->s_socso,
                'eis_enabled'       => $this->s_eis,
                'pcb_enabled'       => $this->s_pcb,
                'epf_employee_rate_override' => $this->s_epf_override !== '' ? round((float) $this->s_epf_override, 2) : null,
                'pcb_category'        => $this->s_pcb_category,
                'children'            => (int) $this->s_children,
                'monthly_zakat'       => round((float) $this->s_zakat, 2),
                'annual_other_relief' => round((float) $this->s_other_relief, 2),
            ]
        );

        $this->employee->refresh();
        $this->showStatutory = false;
        session()->flash('success', 'Statutory details saved.');
    }

    // ── Allowances and deductions ─────────────────────────────────────────

    public function openAssign(): void
    {
        $this->resetAssignForm();
        $this->a_from = now()->startOfMonth()->toDateString();
        $this->showAssign = true;
    }

    public function editAssignment(int $id): void
    {
        $row = EmployeePayComponent::where('employee_id', $this->employee->id)->findOrFail($id);

        $this->assignmentId   = $row->id;
        $this->a_component_id = (string) $row->pay_component_id;
        $this->a_amount       = (string) (float) $row->amount;
        $this->a_from         = $row->effective_from->toDateString();
        $this->a_to           = $row->effective_to?->toDateString() ?? '';
        $this->a_notes        = $row->notes ?? '';
        $this->showAssign     = true;
    }

    public function saveAssignment(): void
    {
        $this->validate([
            'a_component_id' => 'required|exists:pay_components,id',
            'a_amount'       => 'required|numeric|min:0|max:9999999999.99',
            'a_from'         => 'required|date',
            'a_to'           => 'nullable|date',
            'a_notes'        => 'nullable|string|max:255',
        ]);

        // Compared by hand rather than with after_or_equal:a_from — inside a
        // Livewire component that rule resolves the parameter as a literal
        // date string and throws out of Carbon before it can fail the field.
        if ($this->a_to && $this->a_from && $this->a_to < $this->a_from) {
            $this->addError('a_to', 'The end date must not be before the start date.');
            return;
        }

        $data = [
            'company_id'       => $this->employee->company_id,
            'employee_id'      => $this->employee->id,
            'pay_component_id' => (int) $this->a_component_id,
            'amount'           => round((float) $this->a_amount, 2),
            'effective_from'   => $this->a_from,
            'effective_to'     => $this->a_to ?: null,
            'notes'            => $this->a_notes ?: null,
        ];

        if ($this->assignmentId) {
            EmployeePayComponent::where('employee_id', $this->employee->id)
                ->findOrFail($this->assignmentId)->update($data);
            session()->flash('success', 'Updated.');
        } else {
            EmployeePayComponent::create($data);
            session()->flash('success', 'Added.');
        }

        $this->showAssign = false;
        $this->resetAssignForm();
    }

    public function removeAssignment(int $id): void
    {
        EmployeePayComponent::where('employee_id', $this->employee->id)->findOrFail($id)->delete();
        session()->flash('success', 'Removed.');
    }

    private function resetAssignForm(): void
    {
        $this->assignmentId   = null;
        $this->a_component_id = '';
        $this->a_amount       = '';
        $this->a_from         = '';
        $this->a_to           = '';
        $this->a_notes        = '';
        $this->resetValidation();
    }

    // ── Salary revisions ──────────────────────────────────────────────────

    public function openRevision(): void
    {
        $this->r_amount       = $this->employee->basic_salary !== null
            ? number_format((float) $this->employee->basic_salary, 2, '.', '') : '';
        $this->r_pay_type     = $this->employee->pay_type ?: 'monthly';
        $this->r_effective_on = now()->addMonthNoOverflow()->startOfMonth()->toDateString();
        $this->r_reason       = '';
        $this->resetValidation();
        $this->showRevision   = true;
    }

    public function submitRevision(): void
    {
        $this->validate([
            'r_amount'       => 'required|numeric|min:0|max:9999999999.99',
            'r_pay_type'     => 'required|in:' . implode(',', array_keys(Employee::PAY_TYPES)),
            'r_effective_on' => 'required|date',
            'r_reason'       => 'nullable|string|max:255',
        ]);

        $revision = SalaryRevision::create([
            'company_id'    => $this->employee->company_id,
            'employee_id'   => $this->employee->id,
            // Frozen at request time: reading these off the employee later
            // would show the same figure on both sides after a second change.
            'from_amount'   => $this->employee->basic_salary,
            'from_pay_type' => $this->employee->pay_type,
            'to_amount'     => round((float) $this->r_amount, 2),
            'to_pay_type'   => $this->r_pay_type,
            'effective_on'  => $this->r_effective_on,
            'reason'        => $this->r_reason ?: null,
            'requested_by'  => Auth::id(),
        ]);

        $this->showRevision = false;

        // Someone who can approve does not need to queue behind themselves.
        if (SalaryRevision::canApprove()) {
            $revision->update([
                'status'      => SalaryRevision::APPROVED,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'review_note' => 'Approved by the requester.',
            ]);
            $applied = $revision->applyIfDue();
            $this->employee->refresh();

            session()->flash('success', $applied
                ? 'Salary updated.'
                : 'Salary change approved. It takes effect on ' . $revision->effective_on->format('d M Y') . '.');
            return;
        }

        session()->flash('success', 'Salary change submitted for approval.');
    }

    public function render()
    {
        $components = PayComponent::active()->ordered()->get();

        $assignments = $this->employee->payComponents()
            ->with('component')
            ->orderByDesc('effective_from')
            ->get();

        $revisions = $this->employee->salaryRevisions()
            ->with(['requester:id,name', 'reviewer:id,name'])
            ->get();

        // This month's figures for this one employee, through exactly the same
        // service the list uses — a payslip preview that cannot drift from it.
        $summary = app(CompensationSummary::class)->forMonth(
            Employee::query()->whereKey($this->employee->id),
            $this->employee->company_id,
            now()
        );

        return view('livewire.hr.employee-compensation', [
            'components'  => $components,
            'assignments' => $assignments,
            'revisions'   => $revisions,
            'settings'    => CompensationSetting::forCompany($this->employee->company_id),
            'canApprove'  => SalaryRevision::canApprove(),
            'statutory'   => StatutorySetting::forCompany($this->employee->company_id),
            'thisMonth'   => $summary['rows']->first(),
            'monthLabel'  => $summary['from']->format('F Y'),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Compensation — ' . $this->employee->name]);
    }
}
