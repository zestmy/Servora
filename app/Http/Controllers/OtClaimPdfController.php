<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\OtEmployee;
use App\Models\OvertimeClaim;
use App\Models\OvertimeClaimApprover;
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

        // Resolve approver names for this outlet
        $approverNames = OvertimeClaimApprover::with('user')
            ->where('outlet_id', $outletId)
            ->orWhereNull('outlet_id')
            ->get()
            ->pluck('user.name')
            ->filter()
            ->unique()
            ->implode(' / ');

        if ($employee === 'all') {
            // All employees — grouped PDF
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

                $grouped[] = [
                    'employee'    => $emp,
                    'claims'      => $claims,
                    'totalHours'  => $claims->sum('total_ot_hours'),
                    'hoursByType' => $claims->groupBy('ot_type')->map(fn ($g) => $g->sum('total_ot_hours')),
                ];
            }

            $pdf = Pdf::loadView('pdf.ot-claims-all', compact(
                'company', 'grouped', 'from', 'to', 'approverNames'
            ))->setPaper('a4', 'portrait');

            return $pdf->download('ot-claims-all.pdf');
        }

        // Single employee
        $emp = OtEmployee::findOrFail((int) $employee);

        $query = OvertimeClaim::with(['employee', 'submitter', 'approver', 'outlet'])
            ->where('employee_id', $emp->id)
            ->where('status', 'approved');

        if ($from) $query->where('claim_date', '>=', $from);
        if ($to)   $query->where('claim_date', '<=', $to);

        $claims = $query->orderBy('claim_date')->get();

        $totalHours  = $claims->sum('total_ot_hours');
        $hoursByType = $claims->groupBy('ot_type')->map(fn ($g) => $g->sum('total_ot_hours'));

        // Submitter = person who created/submitted the claims (verified by)
        $submitterName = $claims->pluck('submitter.name')->filter()->unique()->implode(' / ');

        $pdf = Pdf::loadView('pdf.ot-claims', compact(
            'company', 'employee', 'claims', 'totalHours', 'hoursByType', 'submitterName', 'approverNames'
        ))->setPaper('a4', 'portrait');

        $name = str_replace(' ', '-', strtolower($emp->name));

        return $pdf->download("ot-claims-{$name}.pdf");
    }
}
