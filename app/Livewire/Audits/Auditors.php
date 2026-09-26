<?php

namespace App\Livewire\Audits;

use App\Models\AuditAuditor;
use App\Models\Employee;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Audit settings: who the company's appointed auditors are.
 *
 * Feeds the `auditor` header field on audit forms. Company-wide, because a
 * QA auditor from head office is not "staff of the audited outlet" — that is
 * what the `employee` header field is for.
 */
class Auditors extends Component
{
    public string $search = '';

    private function requireManage(): void
    {
        abort_unless(Auth::user()?->canDo('audits.manage'), 403);
    }

    public function appoint(int $employeeId): void
    {
        $this->requireManage();

        $employee = Employee::where('company_id', Auth::user()->company_id)->findOrFail($employeeId);

        AuditAuditor::firstOrCreate(
            ['company_id' => $employee->company_id, 'employee_id' => $employee->id],
            ['appointed_by' => Auth::id()]
        );

        $this->search = '';
        session()->flash('success', $employee->name . ' appointed as an auditor.');
    }

    public function remove(int $id): void
    {
        $this->requireManage();

        AuditAuditor::findOrFail($id)->delete();

        session()->flash('success', 'Removed from the auditor list. Audits already conducted keep the name.');
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        $auditors = AuditAuditor::with(['employee.outlet', 'appointedBy'])
            ->get()
            ->filter(fn ($a) => $a->employee)
            ->sortBy(fn ($a) => $a->employee->name)
            ->values();

        $results = collect();

        if (mb_strlen($this->search) >= 2) {
            $term = '%' . $this->search . '%';

            $results = Employee::where('company_id', $companyId)
                ->where('is_active', true)
                ->whereNotIn('id', $auditors->pluck('employee_id'))
                ->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('staff_id', 'like', $term)
                    ->orWhere('designation', 'like', $term))
                ->with('outlet')
                ->orderBy('name')
                ->limit(12)
                ->get();
        }

        return view('livewire.audits.auditors', [
            'auditors'  => $auditors,
            'results'   => $results,
            'canManage' => Auth::user()->canDo('audits.manage'),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Appointed Auditors']);
    }
}
