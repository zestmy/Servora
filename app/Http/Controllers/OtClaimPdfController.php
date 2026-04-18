<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeClaim;
use App\Models\OvertimeClaimApprover;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class OtClaimPdfController extends Controller
{
    public function __invoke(Request $request, string $employee)
    {
        $user     = $request->user();
        $company  = Company::find($user->company_id);
        $outletId = $user->activeOutletId();

        $from = $request->input('from');
        $to   = $request->input('to');

        if ($employee === 'all') {
            $employees = Employee::with('section')
                ->where('outlet_id', $outletId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $grouped = [];
            foreach ($employees as $emp) {
                $query = OvertimeClaim::with(['employee', 'submitter', 'approver', 'outlet'])
                    ->where('employee_id', $emp->id)
                    ->where('status', 'approved');

                if ($from) $query->where('claim_date', '>=', $from);
                if ($to)   $query->where('claim_date', '<=', $to);

                $claims = $query->orderBy('claim_date')->get();
                if ($claims->isEmpty()) continue;

                $submitters = $claims->pluck('submitter')->filter()->unique('id');

                $grouped[] = [
                    'employee'    => $emp,
                    'claims'      => $claims,
                    'totalHours'  => $claims->sum('total_ot_hours'),
                    'hoursByType' => $claims->groupBy('ot_type')->map(fn ($g) => $g->sum('total_ot_hours')),
                    'submitters'  => $submitters,
                    // Approvers that could approve this specific employee's section at this outlet.
                    'approvers'   => $this->approversFor($outletId, $emp->section_id),
                ];
            }

            $pdf = Pdf::loadView('pdf.ot-claims-all', compact(
                'company', 'grouped', 'from', 'to'
            ))->setPaper('a4', 'portrait');

            return $pdf->download('ot-claims-all.pdf');
        }

        // Single employee
        $employee = Employee::with('section')->findOrFail((int) $employee);

        $query = OvertimeClaim::with(['employee', 'submitter', 'approver', 'outlet'])
            ->where('employee_id', $employee->id)
            ->where('status', 'approved');

        if ($from) $query->where('claim_date', '>=', $from);
        if ($to)   $query->where('claim_date', '<=', $to);

        $claims = $query->orderBy('claim_date')->get();

        $totalHours  = $claims->sum('total_ot_hours');
        $hoursByType = $claims->groupBy('ot_type')->map(fn ($g) => $g->sum('total_ot_hours'));

        // Unique submitters
        $submitters = $claims->pluck('submitter')->filter()->unique('id');

        // Approvers scoped to this employee's outlet + section.
        $approvers = $this->approversFor($outletId, $employee->section_id);

        $pdf = Pdf::loadView('pdf.ot-claims', compact(
            'company', 'employee', 'claims', 'totalHours', 'hoursByType', 'submitters', 'approvers', 'from', 'to'
        ))->setPaper('a4', 'portrait');

        $name = str_replace(' ', '-', strtolower($employee->name));

        return $pdf->download("ot-claims-{$name}.pdf");
    }

    /**
     * Users who are approvers for the given (outlet, section) pair — matching
     * the same null-as-"any" semantics the app uses for approval checks.
     * Filtering by section prevents e.g. a BOH-only approver from being
     * listed on an FOH claim's PDF.
     */
    private function approversFor(?int $outletId, ?int $sectionId)
    {
        return OvertimeClaimApprover::with('user')
            ->where(function ($q) use ($outletId) {
                $q->whereNull('outlet_id')->orWhere('outlet_id', $outletId);
            })
            ->where(function ($q) use ($sectionId) {
                if ($sectionId === null) {
                    $q->whereNull('section_id');
                } else {
                    $q->whereNull('section_id')->orWhere('section_id', $sectionId);
                }
            })
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id');
    }
}
