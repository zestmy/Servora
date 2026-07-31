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

        $sets->load('lines:id,label_set_id,storage_state');

        // Resolved once for the whole sheet rather than per card.
        $temperatures = \App\Models\LabelSetting::temperaturesFor($outlet->company_id);

        $cards = $sets->map(fn (LabelSet $set) => [
            'set'      => $set,
            'image'    => $qr->svgFor($set),
            'url'      => $qr->urlFor($set),
            // Resolved on the model: an explicit per-set choice wins, and
            // falls back to whatever the set's own items say.
            'storages' => $set->storageForLabel($temperatures),
        ]);

        $size = $request->query('size', '4x6');

        return view('labels.qr-sheet', [
            'outlet' => $outlet,
            'cards'  => $cards,
            'size'   => array_key_exists($size, self::SIZES) ? $size : '4x6',
            'sizes'  => self::SIZES,
        ]);
    }

    /**
     * Page sizes in EXPLICIT MILLIMETRES, not CSS keywords.
     *
     * "A6" and "4x6 inch" are both sold as airway-bill label stock and they
     * are not the same: 105×148 against 101.6×152.4, a different size and a
     * different aspect ratio. Asking for `size: A6` when the printer holds
     * 4×6 makes the browser rotate and shrink the page to fit, which is
     * exactly what it did — a postage-stamp label in the corner of a blank
     * one.
     *
     * Naming the millimetres removes the guesswork: the page is declared as
     * precisely the media loaded, so there is nothing to reconcile.
     */
    public const SIZES = [
        '4x6' => ['w' => 101.6, 'h' => 152.4, 'label' => '4 × 6 in', 'mode' => 'single'],
        'a6'  => ['w' => 105.0, 'h' => 148.0, 'label' => 'A6',       'mode' => 'single'],
        'a4'  => ['w' => 210.0, 'h' => 297.0, 'label' => 'A4 sheet', 'mode' => 'grid'],
    ];

}
