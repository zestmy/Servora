<?php

namespace App\Http\Controllers;

use App\Models\AssetCount;
use App\Models\Company;
use App\Services\Pdf\PdfImage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The asset count sheet — the paper somebody walks the outlet with.
 *
 * Built like StockTakeCountSheetController, with one thing that sheet has no
 * use for: A PHOTOGRAPH PER ROW. That is the whole reason this export is worth
 * having on paper rather than reading names off a tablet. An ingredient sheet
 * lists things a chef already knows by name; an asset sheet lists forty
 * smallwares, and "MIXING BOWL" against "STAINLESS MIXING BOWL" is not a
 * difference a name settles when both are in front of you.
 *
 * NO EXPECTED QUANTITIES ARE PRINTED, matching the stock-take sheet and the
 * form's own blind-count toggle. A sheet that says what it expects gets that
 * number written back on it. The variance is worked out on screen afterwards,
 * against what the counter actually found.
 *
 * Photos go through PdfImage::thumb() rather than a URL or a raw file path:
 * dompdf would otherwise fetch each one over the network (remote fetching is
 * off) or embed it at full camera resolution, and there is one per row. See
 * that class for what a 200-row sheet costs in memory if you don't.
 */
class AssetCountSheetController extends Controller
{
    public function __invoke(Request $request, int $id)
    {
        $company = Company::find(Auth::user()->company_id);

        $count = AssetCount::with([
            'outlet',
            'department',
            'createdBy',
            'lines.asset.uom',
            'lines.asset.category.parent',
        ])->findOrFail($id);

        abort_unless(Auth::user()->canAccessOutlet($count->outlet_id), 403);

        $images = app(PdfImage::class);

        /*
         * Grouped by category the way the screen orders the sheet, so the paper
         * and the form walk the outlet in the same order — a counter working
         * from one and typing into the other should never have to hunt.
         *
         * A parent category swallows its children, because "Equipment" is the
         * shelf somebody walks, not "Equipment ▸ Refrigeration".
         */
        $groupedLines = $count->lines
            ->sortBy(fn ($l) => ($l->asset?->category?->parent?->name ?? $l->asset?->category?->name ?? 'ZZZ')
                . ' ' . ($l->asset?->name ?? ''))
            ->groupBy(function ($line) {
                $category = $line->asset?->category;

                return $category?->parent?->name ?? $category?->name ?? 'Uncategorised';
            });

        // Resolved once per ASSET rather than per row: the same asset cannot
        // appear twice on one count, but this keeps the decode out of the view
        // either way, where a failure would be a broken page rather than a
        // missing picture.
        $thumbs = $count->lines
            ->pluck('asset')
            ->filter()
            ->unique('id')
            ->mapWithKeys(fn ($asset) => [$asset->id => $this->fitted($images->thumb($asset->image_path))])
            ->filter()
            ->all();

        $pdf = Pdf::loadView('pdf.asset-count-sheet', compact('count', 'company', 'groupedLines', 'thumbs'))
            ->setPaper('a4', 'portrait');

        $ref = $count->reference_number ?: 'AC-' . $count->id;

        return $pdf->download("Asset-Count-Sheet-{$ref}.pdf");
    }

    /** The side of the square each thumbnail is fitted inside, in CSS px. */
    private const BOX_PX = 34;

    /**
     * A data URI plus the width and height to draw it at.
     *
     * dompdf has no object-fit, so an <img> given both a width and a height
     * STRETCHES to them — a 4:3 photo printed in a square box comes out visibly
     * squashed, which on a sheet whose whole job is telling two similar objects
     * apart is the one thing it must not do. Setting only one dimension keeps
     * the aspect but lets a wide photo run out of its column.
     *
     * So the fit is worked out here, where the real pixels are known, and the
     * view is handed numbers it can print without deciding anything.
     *
     * @return array{src: string, w: int, h: int}|null
     */
    private function fitted(?string $dataUri): ?array
    {
        if (! $dataUri) {
            return null;
        }

        $comma = strpos($dataUri, ',');
        $bytes = $comma === false ? false : base64_decode(substr($dataUri, $comma + 1), true);
        $size  = $bytes === false ? false : @getimagesizefromstring($bytes);

        // Unmeasurable: draw it square rather than dropping it. A picture with
        // the wrong proportions still identifies the thing; no picture does not.
        if (! $size || $size[0] < 1 || $size[1] < 1) {
            return ['src' => $dataUri, 'w' => self::BOX_PX, 'h' => self::BOX_PX];
        }

        [$w, $h] = $size;

        $scale = min(self::BOX_PX / $w, self::BOX_PX / $h);

        return [
            'src' => $dataUri,
            'w'   => max(1, (int) round($w * $scale)),
            'h'   => max(1, (int) round($h * $scale)),
        ];
    }
}
