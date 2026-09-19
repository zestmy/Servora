<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Services\Reports\WeeklyWipReview;
use App\Services\Reports\WipReviewParameters as Params;
use App\Traits\ScopesToActiveOutlet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The whole WIP review — every slide — as one PDF, for the meeting pack.
 *
 * Built by the same service from the same choices the screen had, read off the
 * query string and normalised by WipReviewParameters, so the file and the
 * screen cannot disagree. Pay stays gated exactly as on screen: overtime cost
 * and labour cost are never computed for a viewer without hr.compensation, so
 * they cannot reach the PDF either. Outlets are limited to the viewer's own
 * access, whatever the URL says. Charts are tables and bars — dompdf has no
 * canvas.
 */
class WipReviewPdfController extends Controller
{
    use ScopesToActiveOutlet;

    public function __invoke(Request $request)
    {
        $user    = Auth::user();
        $mode    = Params::mode($request->query('mode'));
        $monthly = $mode === WeeklyWipReview::MONTH;
        $anchor  = $monthly ? Params::month($request->query('month')) : Params::week($request->query('week'));
        $count   = $monthly ? Params::months($request->query('months')) : Params::weeks($request->query('weeks'));

        $selected  = $this->selectedOutletId($request->query('outlet'));
        $outletIds = $selected !== null ? [$selected] : $this->availableOutletIds();

        $report = app(WeeklyWipReview::class)->build(
            (int) $user->company_id,
            $outletIds,
            $anchor,
            $count,
            $mode,
            $monthly && $request->boolean('drafts', true),  // included unless drafts=0, as on screen
            Employee::canViewPay($user),
        );

        $scopeLabel = $selected !== null
            ? (Outlet::withoutGlobalScopes()->find($selected)?->name ?? 'One outlet')
            : 'All outlets';

        $pdf = Pdf::loadView('pdf.wip-review', [
            'report'     => $report,
            'company'    => Company::find($user->company_id),
            'scopeLabel' => $scopeLabel,
            'exportedBy' => $user->name,
        ])->setPaper('a4', 'landscape');

        $filename = ($monthly ? 'Monthly' : 'Weekly') . '-WIP-Review-' . $report['current']['start']
            . ($selected !== null ? '-' . Str::slug($scopeLabel) : '') . '.pdf';

        return $pdf->download($filename);
    }
}
