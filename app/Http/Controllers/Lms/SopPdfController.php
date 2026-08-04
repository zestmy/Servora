<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Services\Pdf\SopExportBuilder;
use Illuminate\Support\Facades\Auth;

class SopPdfController extends Controller
{
    /**
     * Ceiling for a render, above php.ini's 256M.
     *
     * dompdf keeps a frame object per DOM node for the whole document and
     * only serialises at the end, so its peak tracks document structure, not
     * page count alone: a 61-recipe category export measures ~198 MB with
     * every image stripped out, and ~240 MB with them. 256M left no headroom
     * and was the 500.
     *
     * Deliberately not higher. This is a 2 GB box running five php-fpm
     * children, so a ceiling generous enough for the whole 203-recipe catalogue
     * (~660 MB by the same measurement) would let two concurrent exports take
     * the server down — that export goes through the queue instead, see
     * App\Jobs\GenerateSopExport. A limit is a ceiling and not a reservation,
     * so this costs nothing on the requests that don't need it.
     */
    private const RENDER_MEMORY = '512M';

    public function __construct(private SopExportBuilder $builder)
    {
    }

    public function single(int $id)
    {
        ini_set('memory_limit', self::RENDER_MEMORY);

        [$user, $traineeOutletIds] = $this->viewer();

        $built = $this->builder->single($user, $id, $traineeOutletIds);

        return $built['pdf']->download($built['filename']);
    }

    public function all()
    {
        ini_set('memory_limit', self::RENDER_MEMORY);

        [$user, $traineeOutletIds] = $this->viewer();

        $built = $this->builder->bulk($user, [
            'prep'           => request()->boolean('prep'),
            'prep_category'  => (int) request('prep_category') ?: null,
            'category'       => trim((string) request('category')) ?: null,
            'category_group' => (int) request('category_group') ?: null,
        ], $traineeOutletIds);

        return $built['pdf']->download($built['filename']);
    }

    /**
     * Who is asking, and which outlets they may see.
     *
     * A trainee on the LMS guard is limited to the outlets assigned to them;
     * a staff user on the web guard passes an empty list, which the recipe
     * scope reads as "no outlet restriction".
     */
    private function viewer(): array
    {
        if (Auth::guard('lms')->check()) {
            $user = Auth::guard('lms')->user();

            return [$user, $user->accessibleOutletIds()];
        }

        return [Auth::user(), []];
    }
}
