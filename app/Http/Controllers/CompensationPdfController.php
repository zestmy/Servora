<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Section;
use App\Models\StatutorySetting;
use App\Services\Hr\CompensationSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Monthly compensation as a PDF.
 *
 * Built from the same CompensationSummary the screen renders, so the paper and
 * the screen cannot disagree about what anyone is owed — which is the whole
 * reason a payroll clerk would print it.
 *
 * Landscape: with statutory columns switched on the table is basic, allowances,
 * deductions, OT, gross, EPF, SOCSO, EIS, PCB and net, and portrait would
 * squeeze the ringgit figures until they wrap.
 */
class CompensationPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $user      = $request->user();
        $company   = Company::find($user->company_id);
        $accessible = $user->accessibleOutletIds();

        try {
            $month = Carbon::createFromFormat('Y-m', (string) $request->input('month'))->startOfMonth();
        } catch (\Throwable $e) {
            $month = now()->startOfMonth();
        }

        // Same scoping as the screen, and hard-bounded by outlet access
        // regardless of what the query string asks for.
        $outletId  = (int) $request->input('outlet');
        $sectionId = (int) $request->input('section');

        $query = Employee::query()->whereIn('outlet_id', $accessible ?: [0]);
        if ($outletId && in_array($outletId, $accessible, true)) {
            $query->where('outlet_id', $outletId);
        } else {
            $outletId = 0;
        }
        if ($sectionId) {
            $query->where('section_id', $sectionId);
        }

        $summary   = app(CompensationSummary::class)->forMonth($query, $user->company_id, $month);
        $statutory = StatutorySetting::forCompany($user->company_id);

        $scope = [
            'outlet'  => $outletId ? Outlet::find($outletId)?->name : 'All outlets',
            'section' => $sectionId ? Section::find($sectionId)?->name : 'All sections',
        ];

        $pdf = Pdf::loadView('pdf.compensation-summary', [
            'company'     => $company,
            'summary'     => $summary,
            'statutory'   => $statutory,
            'showStatutory' => $statutory->anyEnabled(),
            'periodLabel' => $month->format('F Y'),
            'scope'       => $scope,
            'exportedBy'  => $user->name ?? $user->email,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('compensation-' . $month->format('Y-m') . '.pdf');
    }
}
