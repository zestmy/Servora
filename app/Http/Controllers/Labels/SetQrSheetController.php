<?php

namespace App\Http\Controllers\Labels;

use App\Http\Controllers\Controller;
use App\Models\LabelSet;
use App\Models\Outlet;
use App\Services\Labels\LabelQrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A printable sheet of set QR codes, cut up and stuck on chiller doors.
 *
 * Plain HTML with a print stylesheet rather than a PDF: these are cut out
 * with scissors and taped to stainless steel, so what matters is that the
 * codes come out big and crisp on A4, not that the file is portable.
 */
class SetQrSheetController extends Controller
{
    /**
     * Permission is enforced by the route's `can:labels.manage` middleware,
     * as everywhere else in this module. What is checked HERE is ownership:
     * that the requested outlet belongs to the signed-in company, so a
     * manager cannot print another company's stations by changing the query
     * string.
     */
    public function __invoke(Request $request, LabelQrService $qr)
    {
        $outletId = (int) $request->query('outlet');
        $singleId = $request->query('set');

        // CompanyScope keeps this to the signed-in company; the outlet check
        // is what stops a manager printing another outlet's stations.
        $outlet = Outlet::where('company_id', Auth::user()->company_id)
            ->findOrFail($outletId);

        $sets = LabelSet::forOutlet($outlet->id)
            ->when($singleId, fn ($q) => $q->where('id', $singleId))
            ->where('is_active', true)
            ->withCount('lines')
            ->ordered()
            ->get();

        abort_if($sets->isEmpty(), 404, 'No print sets to show for this outlet.');

        $cards = $sets->map(fn (LabelSet $set) => [
            'set'   => $set,
            'image' => $qr->svgFor($set),
            'url'   => $qr->urlFor($set),
        ]);

        // A6 by default: airway-bill label stock, peel and stick, no
        // scissors. A4 stays available for setting up a whole outlet at once.
        $size = $request->query('size') === 'a4' ? 'a4' : 'a6';

        return view('labels.qr-sheet', [
            'outlet' => $outlet,
            'cards'  => $cards,
            'size'   => $size,
        ]);
    }
}
