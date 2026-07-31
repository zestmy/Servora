<?php

namespace App\Http\Controllers\Labels;

use App\Http\Controllers\Controller;
use App\Models\LabelSet;
use App\Models\Outlet;
use App\Services\Labels\LabelQrService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Set QR labels, stuck on chiller doors and stations.
 *
 * Two outputs from the same data. The HTML page is for choosing a size and
 * seeing what you'll get; the PDF is what should actually be printed.
 *
 * That split exists because browser printing kept losing the argument with
 * the label driver — page size, orientation and scale all get renegotiated
 * at print time, and the label came out rotated and a third of its size
 * however precisely the page was declared. A PDF carries its own MediaBox,
 * so "Actual size" means actual size.
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
        $size = array_key_exists($size, self::SIZES) ? $size : '4x6';
        $page = self::SIZES[$size];

        if ($request->query('format') === 'pdf') {
            return $this->pdf($outlet, $sets, $qr, $temperatures, $page, $size);
        }

        return view('labels.qr-sheet', [
            'outlet' => $outlet,
            'cards'  => $cards,
            'size'   => $size,
            'sizes'  => self::SIZES,
        ]);
    }

    /**
     * The reliable path.
     *
     * Browser printing kept renegotiating page size, orientation and scale
     * with the driver, so an exactly-declared label still came out rotated
     * and a third of its size. A PDF carries its own MediaBox: printed at
     * "Actual size" it is the size it says it is. This is the same
     * mechanism the 70x40 item labels use, and those print correctly.
     */
    private function pdf($outlet, $sets, LabelQrService $qr, array $temperatures, array $page, string $size)
    {
        $margin = $page['mode'] === 'single' ? 5.0 : 12.0;
        $inner  = $page['w'] - ($margin * 2);

        $cards = $sets->map(function (LabelSet $set) use ($qr, $temperatures, $inner) {
            $storages = $set->storageForLabel($temperatures);

            return [
                'set' => $set,
                // PNG, not the SVG the web page uses: dompdf's SVG handling
                // is unreliable and a blank box would be worse than no label.
                'png'      => $qr->pngFor($set),
                'storages' => $storages,
                // The QR yields to the temperature block. A set holding two
                // storage states pushed a fixed-size QR past the bottom of
                // the label and produced a blank second page for every one
                // printed, so the code shrinks instead of the label growing.
                'qr_size'  => round($inner * match (count($storages)) {
                    0, 1    => 0.68,
                    2       => 0.50,
                    default => 0.38,
                }, 1),
            ];
        })->all();

        $pt = 2.834645669;   // points per millimetre

        $pdf = Pdf::loadView('labels.qr-sheet-pdf', [
            'outlet' => $outlet,
            'cards'  => $cards,
            'page'   => $page,
            'margin' => $margin,
            
        ])->setPaper([0, 0, $page['w'] * $pt, $page['h'] * $pt]);

        return $pdf->download(sprintf(
            'set-qr-%s-%s.pdf',
            Str::slug($outlet->name),
            $size
        ));
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
