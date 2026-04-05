<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\OtEmployee;
use App\Models\OvertimeClaim;
use App\Models\OvertimeClaimApprover;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class OtClaimPdfController extends Controller
{
    public function __invoke(Request $request, string $employee)
    {
        $user    = $request->user();
        $company = Company::find($user->company_id);
        $outletId = $user->activeOutletId();

        $from = $request->input('from');
        $to   = $request->input('to');

        // Resolve approvers for this outlet (with designation)
        $approvers = OvertimeClaimApprover::with('user')
            ->where(function ($q) use ($outletId) {
                $q->where('outlet_id', $outletId)->orWhereNull('outlet_id');
            })
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id');

        if ($employee === 'all') {
            $employees = OtEmployee::where('outlet_id', $outletId)
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

                // Unique submitters for this employee's claims
                $submitters = $claims->pluck('submitter')->filter()->unique('id');

                $grouped[] = [
                    'employee'    => $emp,
                    'claims'      => $claims,
                    'totalHours'  => $claims->sum('total_ot_hours'),
                    'hoursByType' => $claims->groupBy('ot_type')->map(fn ($g) => $g->sum('total_ot_hours')),
                    'submitters'  => $submitters,
                ];
            }

            $pdf = Pdf::loadView('pdf.ot-claims-all', compact(
                'company', 'grouped', 'from', 'to', 'approvers'
            ))->setPaper('a4', 'portrait');

            return $pdf->download('ot-claims-all.pdf');
        }

        // Single employee
        $employee = OtEmployee::findOrFail((int) $employee);

        $query = OvertimeClaim::with(['employee', 'submitter', 'approver', 'outlet'])
            ->where('employee_id', $employee->id)
            ->where('status', 'approved');

        if ($from) $query->where('claim_date', '>=', $from);
        if ($to)   $query->where('claim_date', '<=', $to);

        $claims = $query->orderBy('claim_date')->get();

        $totalHours  = $claims->sum('total_ot_hours');
        $hoursByType = $claims->groupBy('ot_type')->map(fn ($g) => $g->sum('total_ot_hours'));

        // Unique submitters (verified by)
        $submitters = $claims->pluck('submitter')->filter()->unique('id');

        $pdf = Pdf::loadView('pdf.ot-claims', compact(
            'company', 'employee', 'claims', 'totalHours', 'hoursByType', 'submitters', 'approvers'
        ))->setPaper('a4', 'portrait');

        $name = str_replace(' ', '-', strtolower($employee->name));

        return $pdf->download("ot-claims-{$name}.pdf");
    }
}
