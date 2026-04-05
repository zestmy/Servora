<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\OtEmployee;
use App\Models\OvertimeClaim;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class OtClaimPdfController extends Controller
{
    public function __invoke(Request $request, int $employeeId)
    {
        $user    = $request->user();
        $company = Company::find($user->company_id);

        $employee = OtEmployee::findOrFail($employeeId);

        $query = OvertimeClaim::with(['employee', 'submitter', 'approver', 'outlet'])
            ->where('employee_id', $employeeId)
            ->where('status', 'approved');

        if ($request->filled('from')) {
            $query->where('claim_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('claim_date', '<=', $request->to);
        }

        $claims = $query->orderBy('claim_date')->get();

        $totalHours = $claims->sum('total_ot_hours');
        $hoursByType = $claims->groupBy('ot_type')->map(fn ($g) => $g->sum('total_ot_hours'));

        $pdf = Pdf::loadView('pdf.ot-claims', compact(
            'company', 'employee', 'claims', 'totalHours', 'hoursByType'
        ))->setPaper('a4', 'portrait');

        $name = str_replace(' ', '-', strtolower($employee->name));

        return $pdf->download("ot-claims-{$name}.pdf");
    }
}
