<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeClaim;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class OtClaimSummaryPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $user     = $request->user();
        $company  = Company::find($user->company_id);
        $outletId = $user->activeOutletId();

        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year',  now()->year);

        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));   // last day of month

        // All approved claims for this outlet in the period, ordered by type then date then employee name
        $claims = OvertimeClaim::with(['employee.section'])
            ->where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->whereBetween('claim_date', [$from, $to])
            ->orderBy('ot_type')
            ->orderBy('claim_date')
            ->get()
            ->sortBy(fn ($c) => [$c->ot_type, $c->claim_date, $c->employee?->name]);

        // Subtotals per OT type
        $otTypes = [
            'normal_day'     => 'Normal Day',
            'rest_day'       => 'Rest Day',
            'public_holiday' => 'Public Holiday',
        ];

        $subtotals = [];
        foreach ($otTypes as $key => $label) {
            $group = $claims->where('ot_type', $key);
            $subtotals[$key] = [
                'label'  => $label,
                'count'  => $group->count(),
                'hours'  => (float) $group->sum('total_ot_hours'),
            ];
        }

        $grandTotalCount = $claims->count();
        $grandTotalHours = (float) $claims->sum('total_ot_hours');

        $periodLabel = date('F Y', strtotime($from));
        $exportedBy  = $user->name ?? $user->email;

        $pdf = Pdf::loadView('pdf.ot-claims-summary', compact(
            'company', 'claims', 'otTypes', 'subtotals',
            'grandTotalCount', 'grandTotalHours',
            'periodLabel', 'from', 'to', 'exportedBy'
        ))->setPaper('a4', 'landscape');

        return $pdf->download("ot-claims-summary-{$year}-{$month}.pdf");
    }
}
