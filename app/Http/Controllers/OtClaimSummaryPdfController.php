<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\OvertimeClaim;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class OtClaimSummaryPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $user    = $request->user();
        $company = Company::find($user->company_id);

        // Use same outlet scope as the Livewire component — cross-outlet roles
        // see all company outlets; everyone else sees only assigned outlets.
        $availableOutletIds = $user->canViewAllOutlets()
            ? \App\Models\Outlet::where('company_id', $user->company_id)->pluck('id')->all()
            : $user->outlets()->pluck('outlets.id')->all();

        // If outlet filter is specified, narrow down to that outlet only
        $outletFilter = $request->input('outlet');
        if ($outletFilter && in_array((int) $outletFilter, $availableOutletIds)) {
            $availableOutletIds = [(int) $outletFilter];
        }

        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year',  now()->year);

        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));

        $otTypeLabels = [
            'normal_day'     => 'Normal Day',
            'rest_day'       => 'Rest Day',
            'public_holiday' => 'Public Holiday',
        ];

        // Fetch all approved claims for the period - filter by EMPLOYEE's outlet, not claim's outlet
        // This ensures claims appear in the correct outlet's report even if claim.outlet_id was set incorrectly
        $claims = OvertimeClaim::with('employee')
            ->join('employees', 'overtime_claims.employee_id', '=', 'employees.id')
            ->whereIn('employees.outlet_id', $availableOutletIds)
            ->where('overtime_claims.status', 'approved')
            ->whereBetween('overtime_claims.claim_date', [$from, $to])
            ->select('overtime_claims.*')
            ->get();

        // Build per-employee summary, sorted by employee name
        // rows = [ { employee, byType: [{ ot_type, label, hours }], totalHours } ]
        $rows = $claims
            ->groupBy('employee_id')
            ->map(function ($empClaims) use ($otTypeLabels) {
                $employee = $empClaims->first()->employee;

                $byType = collect($otTypeLabels)
                    ->map(fn ($label, $key) => [
                        'ot_type' => $key,
                        'label'   => $label,
                        'hours'   => (float) $empClaims->where('ot_type', $key)->sum('total_ot_hours'),
                    ])
                    ->filter(fn ($t) => $t['hours'] > 0)
                    ->values();

                return [
                    'employee'   => $employee,
                    'byType'     => $byType,
                    'totalHours' => (float) $empClaims->sum('total_ot_hours'),
                ];
            })
            ->sortBy(fn ($r) => $r['employee']?->name)
            ->values();

        $grandTotalHours = (float) $claims->sum('total_ot_hours');

        // OT-type column totals for footer
        $typeTotals = [];
        foreach ($otTypeLabels as $key => $label) {
            $typeTotals[$key] = (float) $claims->where('ot_type', $key)->sum('total_ot_hours');
        }

        $periodLabel = date('F Y', strtotime($from));
        $exportedBy  = $user->name ?? $user->email;

        $pdf = Pdf::loadView('pdf.ot-claims-summary', compact(
            'company', 'rows', 'otTypeLabels', 'typeTotals',
            'grandTotalHours', 'periodLabel', 'from', 'to', 'exportedBy'
        ))->setPaper('a4', 'portrait');

        return $pdf->download("ot-claims-summary-{$year}-{$month}.pdf");
    }
}
